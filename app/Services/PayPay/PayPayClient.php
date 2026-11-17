<?php

declare(strict_types=1);

namespace App\Services\PayPay;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use OpenSSLAsymmetricKey;
use RuntimeException;

/**
 * Thin client for the PayPay AO API: builds, encrypts, signs and sends requests.
 * Business logic lives in PayPayService.
 */
class PayPayClient
{
    /** @var array<string, mixed> */
    private array $cfg;

    public function __construct()
    {
        $this->cfg = (array) config('paypay');
    }

    /** @param array<string, mixed> $biz  @return array<string, mixed> biz_content of the response */
    public function instantTrade(array $biz): array
    {
        return $this->call('instant_trade', $biz);
    }

    /** @return array<string, mixed> */
    public function query(string $outTradeNo): array
    {
        return $this->call('trade_query', ['out_trade_no' => $outTradeNo]);
    }

    /** @return array<string, mixed> */
    public function close(string $outTradeNo): array
    {
        return $this->call('trade_close', ['out_trade_no' => $outTradeNo]);
    }

    /** @return array<string, mixed> */
    public function refund(string $origOutTradeNo, string $refundOutTradeNo, string $amount): array
    {
        return $this->call('trade_refund', [
            'orig_out_trade_no' => $origOutTradeNo,
            'out_trade_no' => $refundOutTradeNo,
            'refund_amount' => $amount,
        ]);
    }

    public function verifyNotification(array $data): bool
    {
        $sign = (string) ($data['sign'] ?? '');
        if ($sign === '') {
            return false;
        }
        return PayPayCrypto::verify(PayPayCrypto::signingString($data), $sign, $this->paypayPublicKey());
    }

    /** @param array<string, mixed> $biz  @return array<string, mixed> */
    private function call(string $service, array $biz): array
    {
        $params = [
            'charset' => 'UTF-8',
            'biz_content' => PayPayCrypto::encryptWithPrivateKey(
                json_encode($biz, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
                $this->privateKey()
            ),
            'partner_id' => (string) $this->cfg['partner_id'],
            'service' => $service,
            'request_no' => bin2hex(random_bytes(16)),
            'format' => 'JSON',
            'language' => (string) $this->cfg['language'],
            'sign_type' => 'RSA',
            'version' => '1.0',
            'timestamp' => Carbon::now($this->cfg['timezone'])->format('Y-m-d H:i:s'),
        ];

        // sign the raw values, THEN url-encode every value once (docs 2.4 / 2.5)
        $params['sign'] = PayPayCrypto::sign(PayPayCrypto::signingString($params), $this->privateKey());
        $body = json_encode(array_map('rawurlencode', $params), JSON_THROW_ON_ERROR);

        try {
            $response = Http::withBody($body, 'application/json')
                ->acceptJson()
                ->timeout((int) $this->cfg['http_timeout'])
                ->post((string) $this->cfg['api_url']);
        } catch (ConnectionException $e) {
            throw PayPayException::transport($e->getMessage());
        }

        if (!$response->successful()) {
            throw PayPayException::http($response->status(), $response->body());
        }

        $data = $response->json();
        if (!is_array($data) || ($data['code'] ?? null) !== 'S0001') {
            Log::warning('PayPay API error', ['service' => $service, 'request_no' => $params['request_no'], 'code' => $data['code'] ?? null, 'sub_code' => $data['sub_code'] ?? null]);
            throw PayPayException::api(is_array($data) ? $data : null);
        }

        // NOTE: the response `sign` is not verified: the docs don't say which string it covers.
        // Payment confirmation relies on the signed webhook and on trade_query over TLS.
        return is_array($data['biz_content'] ?? null) ? $data['biz_content'] : [];
    }

    private function privateKey(): OpenSSLAsymmetricKey
    {
        return PayPayCrypto::loadPrivateKey($this->keyMaterial('private_key'));
    }

    private function paypayPublicKey(): OpenSSLAsymmetricKey
    {
        return PayPayCrypto::loadPublicKey($this->keyMaterial('public_key'));
    }

    private function keyMaterial(string $name): string
    {
        $path = $this->cfg[$name . '_path'] ?? null;
        if ($path) {
            $path = $this->absoluteKeyPath((string) $path);
            $content = @file_get_contents($path);
            if ($content === false) {
                throw new RuntimeException("PayPay: cannot read {$name} file");
            }
            return $content;
        }
        $inline = $this->cfg[$name] ?? null;
        if (!$inline) {
            throw new RuntimeException("PayPay: {$name} is not configured");
        }
        return (string) $inline;
    }

    /** Relative paths in .env are resolved from the project root. `artisan serve` runs with cwd = public/. */
    private function absoluteKeyPath(string $path): string
    {
        $path = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
        $isAbsolute = str_starts_with($path, DIRECTORY_SEPARATOR)
            || str_starts_with($path, '\\')
            || (strlen($path) > 2 && ctype_alpha($path[0]) && $path[1] === ':');

        return $isAbsolute ? $path : base_path($path);
    }
}
