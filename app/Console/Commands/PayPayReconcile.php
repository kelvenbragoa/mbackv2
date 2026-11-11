<?php

namespace App\Console\Commands;

use App\Models\PayPayPayment;
use App\Services\PayPay\PayPayClient;
use App\Services\PayPay\PayPayException;
use App\Services\PayPay\PayPayService;
use Illuminate\Console\Command;

class PayPayReconcile extends Command
{
    protected $signature = 'paypay:reconcile {--minutes=3 : only orders older than this}';
    protected $description = 'Query PayPay for pending orders whose notification never arrived';

    public function handle(PayPayClient $client, PayPayService $service): int
    {
        $pending = PayPayPayment::where('status', 'pending')
            ->where('created_at', '<=', now()->subMinutes((int) $this->option('minutes')))
            ->where('created_at', '>=', now()->subDays(3))
            ->orderBy('id')
            ->limit(200)
            ->get();

        foreach ($pending as $p) {
            try {
                $r = $client->query($p->out_trade_no);
                $service->applyTradeStatus($p->out_trade_no, (string) ($r['status'] ?? ''), $r['amount'] ?? null);
            } catch (PayPayException $e) {
                // the order never reached PayPay and the window is over: give up on it
                if ($e->definitive && !$p->trade_no && $p->expires_at && $p->expires_at->isPast()) {
                    $p->update(['status' => 'failed']);
                } else {
                    $this->warn("{$p->out_trade_no}: {$e->getMessage()}");
                }
            }
        }

        $this->info("Checked {$pending->count()} pending payment(s).");
        return self::SUCCESS;
    }
}
