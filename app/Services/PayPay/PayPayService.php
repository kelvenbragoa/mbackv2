<?php

declare(strict_types=1);

namespace App\Services\PayPay;

use App\Events\PayPayPaymentPaid;
use App\Models\PayPayOrder;
use App\Models\PayPayPayment;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use InvalidArgumentException;

class PayPayService
{
    public const METHODS = ['app', 'qr', 'express', 'reference', 'agent'];

    public function __construct(private readonly PayPayClient $client)
    {
    }

    /**
     * Create a payment order at PayPay.
     *
     * @param string $amountAoa  Server-side computed total in kwanzas, e.g. "2500.00". NEVER take this from the app.
     * @param string $method     app (PayPay app wake-up/QR) | qr (Kwik) | express (Multicaixa Express) | reference (Multicaixa Ref.) | agent
     * @param string|null $phone 9 digits; required for express and agent
     * @param string|null $payerName required for agent
     */
    public function initiate(
        string $amountAoa,
        string $subject,
        string $method = 'app',
        ?string $phone = null,
        ?string $payerName = null,
        ?string $payerIp = null,
        ?Model $payable = null,
    ): PayPayPayment {
        $this->validate($amountAoa, $method, $phone, $payerName);

        $cfg = (array) config('paypay');
        $outTradeNo = 'MT' . now()->format('ymdHis') . strtoupper(Str::random(8)); // 22 chars, alphanumeric

        $payment = PayPayPayment::create([
            'out_trade_no' => $outTradeNo,
            'payable_type' => $payable?->getMorphClass(),
            'payable_id' => $payable?->getKey(),
            'method' => $method,
            'amount' => $amountAoa,
            'currency' => 'AOA',
            'subject' => mb_substr($subject, 0, 256),
            'status' => 'pending',
            'phone' => $phone,
            'payer_ip' => $payerIp,
            'expires_at' => $this->expiry((string) $cfg['timeout_express']),
        ]);

        $biz = [
            'cashier_type' => 'SDK',
            'payer_ip' => $payerIp ?: '127.0.0.1',
            'sale_product_code' => (string) $cfg['sale_product_code'],
            'timeout_express' => (string) $cfg['timeout_express'],
            'trade_info' => [
                'currency' => 'AOA',
                'out_trade_no' => $outTradeNo,
                'payee_identity' => (string) $cfg['partner_id'],
                'payee_identity_type' => '1',
                'price' => $amountAoa,
                'quantity' => '1',
                'subject' => $payment->subject,
                'total_amount' => $amountAoa,
            ],
        ];
        if (!empty($cfg['return_url'])) {
            $biz['return_url'] = (string) $cfg['return_url'];
        }
        if ($payMethod = $this->payMethod($method, $amountAoa, $phone, $payerName)) {
            $biz['pay_method'] = $payMethod;
        }

        try {
            $result = $this->client->instantTrade($biz);
        } catch (PayPayException $e) {
            if ($e->definitive) {
                $payment->update(['status' => 'failed']);
            }
            // non-definitive (timeout/5xx): leave pending, the reconcile job will find out
            throw $e;
        }

        $payment->update([
            'trade_no' => $result['trade_no'] ?? null,
            'gateway_response' => $result,
        ]);

        return $payment->refresh();
    }

    /** What the app/web needs to show to the customer. */
    public function checkoutPayload(PayPayPayment $p): array
    {
        $r = $p->gateway_response ?? [];
        $payload = [
            'order' => $p->out_trade_no,
            'method' => $p->method,
            'amount' => $p->amount,
            'currency' => $p->currency,
            'status' => $p->status,
            'expires_at' => $p->expires_at?->toIso8601String(),
        ];

        return $payload + match ($p->method) {
            'app' => [
                'dynamic_link' => $r['dynamic_link'] ?? null,   // open in system browser / QR code on desktop
                'deeplink' => isset($r['trade_token']) ? 'paypayao://trade/pay?action=pay&tradeToken=' . $r['trade_token'] : null,
            ],
            'qr' => [
                'qr_link' => $r['link'] ?? null,                // generate the QR from this
                'name' => $r['name'] ?? null,
                'iban' => $r['iban'] ?? null,
                'code' => $r['numUniqueId'] ?? null,
            ],
            'reference' => [
                'entity_id' => $r['entity_id'] ?? null,         // show both to the customer
                'reference_id' => $r['reference_id'] ?? null,
            ],
            'agent' => [
                'agent_transaction_code' => $r['agent_transaction_code'] ?? null,
            ],
            default => [], // express: the customer approves inside Multicaixa Express
        };
    }

    /** Refund a paid payment. The final result arrives by notification (REFUND_SUCCESS / REFUND_FAIL). */
    public function refund(PayPayPayment $payment, ?string $amount = null): PayPayPayment
    {
        if ($payment->status !== 'paid') {
            throw new InvalidArgumentException('Only paid payments can be refunded');
        }
        if ($payment->refund_out_trade_no) {
            throw new InvalidArgumentException('This payment already has a refund request');
        }
        $amount ??= number_format((float) $payment->amount, 2, '.', '');
        $this->assertAmount($amount);
        if ((float) $amount > (float) $payment->amount) {
            throw new InvalidArgumentException('Refund exceeds the original amount');
        }

        $refundNo = $payment->out_trade_no . 'R1';
        $this->client->refund($payment->out_trade_no, $refundNo, $amount);
        $payment->update(['refund_out_trade_no' => $refundNo, 'refund_status' => 'pending']);

        return $payment->refresh();
    }

    /** Verifies the signature (PayPay public key) of a notification body already parsed to an array. */
    public function verifyNotification(array $data): bool
    {
        return $this->client->verifyNotification($data);
    }

    /** Applies a verified notification. Idempotent: safe to call for every retry. */
    public function handleNotification(array $data): void
    {
        $status = (string) ($data['status'] ?? '');

        // refund notifications carry the original order number
        if (!empty($data['orig_out_trade_no'])) {
            $this->applyRefundStatus((string) $data['orig_out_trade_no'], $status);
            return;
        }

        $out = (string) ($data['out_trade_no'] ?? '');
        if ($out === '' || !$this->applyTradeStatus($out, $status, $data['amount'] ?? null)) {
            Log::warning('PayPay notification for unknown order', ['out_trade_no' => $out, 'status' => $status]);
        }
    }

    /** Used by webhook and by the reconcile command. Returns false if the order is unknown. */
    public function applyTradeStatus(string $outTradeNo, string $status, ?string $amount = null): bool
    {
        $paid = false;

        $payment = DB::transaction(function () use ($outTradeNo, $status, $amount, &$paid) {
            $p = PayPayPayment::where('out_trade_no', $outTradeNo)->lockForUpdate()->first();
            if (!$p || $p->status !== 'pending') {
                return $p; // unknown, or already settled: nothing to do
            }

            if ($status === 'TRADE_FINISHED') {
                if ($amount !== null && abs((float) $amount - (float) $p->amount) > 0.005) {
                    Log::critical('PayPay amount mismatch', ['out_trade_no' => $outTradeNo, 'expected' => $p->amount, 'got' => $amount]);
                    $p->update(['status' => 'review']);
                    return $p;
                }
                $p->update(['status' => 'paid', 'paid_at' => now()]);
                $paid = true;
            } elseif ($status === 'TRADE_CLOSED') {
                $p->update(['status' => 'closed']);
            }
            // TRADE_SUCCESS (paid, not yet settled) and WAIT_BUYER_PAY: keep pending

            return $p;
        });

        if ($payment === null) {
            return false;
        }

        $payment = $payment->refresh();
        $this->syncPayableOrderStatus($payment);

        if ($paid) {
            event(new PayPayPaymentPaid($payment)); // after commit
        }
        return true;
    }

    private function syncPayableOrderStatus(PayPayPayment $payment): void
    {
        $payable = $payment->payable;
        if (! $payable instanceof PayPayOrder) {
            return;
        }
        if (in_array($payable->status, ['fulfilled', 'review'], true)) {
            return;
        }

        if ($payment->status === 'closed' && $payable->status === 'pending') {
            $payable->update(['status' => 'closed']);
        } elseif ($payment->status === 'review') {
            $payable->update(['status' => 'review']);
        } elseif ($payment->status === 'failed' && $payable->status === 'pending') {
            $payable->update(['status' => 'failed']);
        }
    }

    private function applyRefundStatus(string $origOutTradeNo, string $status): void
    {
        $map = ['REFUND_SUCCESS' => 'success', 'REFUND_FAIL' => 'failed'];
        if (!isset($map[$status])) {
            return;
        }
        PayPayPayment::where('out_trade_no', $origOutTradeNo)->update(['refund_status' => $map[$status]]);
    }

    /** @return array<string, string>|null */
    private function payMethod(string $method, string $amount, ?string $phone, ?string $payerName): ?array
    {
        $base = ['pay_product_code' => '31', 'amount' => $amount];

        return match ($method) {
            'app' => null,
            'qr' => $base + ['bank_code' => 'QRCODE'],
            'express' => $base + ['bank_code' => 'MUL', 'phone_num' => (string) $phone],
            'reference' => $base + ['bank_code' => 'REF'],
            'agent' => $base + ['bank_code' => 'AGENT', 'phone_num' => (string) $phone, 'memo' => mb_substr((string) $payerName, 0, 32)],
        };
    }

    private function validate(string $amount, string $method, ?string $phone, ?string $payerName): void
    {
        if (!in_array($method, self::METHODS, true)) {
            throw new InvalidArgumentException("Unknown PayPay method: {$method}");
        }
        $this->assertAmount($amount);
        if ((float) $amount <= 0) {
            throw new InvalidArgumentException('Amount must be positive');
        }
        if (in_array($method, ['express', 'agent'], true) && !preg_match('/^\d{9}$/', (string) $phone)) {
            throw new InvalidArgumentException('A 9-digit phone number is required for this method');
        }
        if ($method === 'agent' && trim((string) $payerName) === '') {
            throw new InvalidArgumentException('Payer name is required for agent payments');
        }
        if ($method === 'reference' && (float) $amount <= 1000) {
            throw new InvalidArgumentException('Multicaixa Reference only accepts orders above 1000 Kz');
        }
    }

    private function assertAmount(string $amount): void
    {
        if (!preg_match('/^\d{1,13}(\.\d{1,2})?$/', $amount)) {
            throw new InvalidArgumentException('Amount must look like "2500" or "2500.00"');
        }
    }

    private function expiry(string $timeout): \Illuminate\Support\Carbon
    {
        if (!preg_match('/^(\d+)([mhd])$/', $timeout, $m)) {
            return now()->addHours(2);
        }
        return match ($m[2]) {
            'm' => now()->addMinutes((int) $m[1]),
            'h' => now()->addHours((int) $m[1]),
            'd' => now()->addDays((int) $m[1]),
        };
    }
}
