<?php

declare(strict_types=1);

namespace App\Services\PayPay;

use RuntimeException;

class PayPayException extends RuntimeException
{
    /**
     * true  = PayPay answered and refused the request (the order does NOT exist on their side)
     * false = we don't know (timeout, network, 5xx): the order may exist, so reconcile later
     */
    public function __construct(string $message, public readonly bool $definitive, public readonly ?string $apiCode = null)
    {
        parent::__construct($message);
    }

    public static function transport(string $message): self
    {
        return new self('PayPay transport error: ' . $message, false);
    }

    public static function http(int $status, string $body): self
    {
        return new self("PayPay HTTP {$status}: " . mb_substr($body, 0, 300), $status < 500 && $status !== 408 && $status !== 429);
    }

    /** @param array<string, mixed>|null $data */
    public static function api(?array $data): self
    {
        $code = (string) ($data['sub_code'] ?? $data['code'] ?? 'unknown');
        $msg = (string) ($data['sub_msg'] ?? $data['msg'] ?? 'no message');
        return new self("PayPay API {$code}: {$msg}", true, $code);
    }
}
