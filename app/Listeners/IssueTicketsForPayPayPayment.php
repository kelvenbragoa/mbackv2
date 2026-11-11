<?php

namespace App\Listeners;

use App\Events\PayPayPaymentPaid;
use App\Models\PayPayOrder;
use App\Models\Sell;
use App\Models\SellDetails;
use App\Models\Ticket;
use App\Models\Transaction;
use App\Support\LiveTicketAccess;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Experimental: turns a paid PayPay live order into official Sell / SellDetails
 * so Event::canWatchLive() works. Idempotent.
 */
class IssueTicketsForPayPayPayment
{
    public function handle(PayPayPaymentPaid $event): void
    {
        $payment = $event->payment->loadMissing('payable');
        $order = $payment->payable;

        if (! $order instanceof PayPayOrder) {
            return;
        }

        DB::transaction(function () use ($order, $payment) {
            /** @var PayPayOrder $order */
            $order = PayPayOrder::query()->lockForUpdate()->with('items')->find($order->id);
            if (! $order || $order->isFulfilled()) {
                return;
            }

            $order->update(['status' => 'paid']);

            $ref = 'PP'.$payment->out_trade_no;
            $user = $order->user()->first();

            foreach ($order->items as $item) {
                $ticket = Ticket::where('id', $item->ticket_id)->lockForUpdate()->first();
                if (! $ticket) {
                    Log::critical('PayPay fulfill: ticket missing', ['order_id' => $order->id, 'ticket_id' => $item->ticket_id]);
                    $order->update(['status' => 'review']);

                    return;
                }

                $available = $ticket->availableQuantity();
                if ($item->qty > $available) {
                    Log::critical('PayPay fulfill: insufficient stock after payment', [
                        'order_id' => $order->id,
                        'ticket_id' => $ticket->id,
                        'qty' => $item->qty,
                        'available' => $available,
                    ]);
                    $order->update(['status' => 'review']);

                    return;
                }

                $sell = Sell::create([
                    'event_id' => $order->event_id,
                    'ticket_id' => $ticket->id,
                    'qty' => $item->qty,
                    'price' => $item->price,
                    'total' => $item->total,
                    'status' => 1,
                    'name' => $order->customer_name,
                    'email' => $order->customer_email,
                    'mobile' => $order->customer_mobile,
                    'user_id' => $order->user_id,
                ]);

                Transaction::create([
                    'sell_id' => $sell->id,
                    'reference' => $ref,
                    'method' => 'paypay',
                    'user_id' => $order->user_id,
                ]);

                $answersList = is_array($item->form_answers) ? $item->form_answers : [];

                for ($i = 0; $i < (int) $item->qty; $i++) {
                    $unitAnswers = $answersList[$i] ?? null;
                    SellDetails::create([
                        'sell_id' => $sell->id,
                        'event_id' => $order->event_id,
                        'ticket_id' => $ticket->id,
                        'status' => SellDetails::STATUS_VALID,
                        'name' => $order->customer_name,
                        'email' => $order->customer_email,
                        'mobile' => $order->customer_mobile,
                        'user_id' => $order->user_id,
                        'form_answers' => is_array($unitAnswers) && $unitAnswers !== [] ? $unitAnswers : null,
                        'access_ends_at' => LiveTicketAccess::accessEndsAtForTicket($ticket),
                    ]);
                }
            }

            $order->update([
                'status' => 'fulfilled',
                'fulfilled_at' => now(),
            ]);

            Log::info('PayPay live tickets issued', [
                'order_id' => $order->id,
                'user_id' => $order->user_id,
                'event_id' => $order->event_id,
                'payment' => $payment->out_trade_no,
                'buyer' => $user?->email,
            ]);
        });
    }
}
