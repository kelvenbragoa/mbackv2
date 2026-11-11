<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\PayPayOrder;
use App\Models\PayPayOrderItem;
use App\Models\Ticket;
use App\Services\PayPay\PayPayException;
use App\Services\PayPay\PayPayService;
use App\Support\LiveTicketAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class PayPayCheckoutController extends Controller
{
    public function __construct(private readonly PayPayService $paypay)
    {
    }

    /**
     * Start an experimental PayPay checkout for live tickets priced in AOA.
     */
    public function store(Request $request): JsonResponse
    {
        $user = Auth::user();
        if (! $user) {
            return response()->json(['message' => 'Inicia sessão para comprar um bilhete de live.'], 401);
        }

        $data = $request->validate([
            'tickets' => ['required', 'array', 'min:1'],
            'tickets.*.id' => ['required', 'integer'],
            'tickets.*.quantity' => ['required', 'integer', 'min:1'],
            'tickets.*.answers' => ['nullable', 'array'],
            'method' => ['nullable', 'string', Rule::in(PayPayService::METHODS)],
            'phone' => ['nullable', 'string', 'max:16'],
            'payer_name' => ['nullable', 'string', 'max:64'],
            'customer_mobile' => ['nullable', 'string', 'max:30'],
        ]);

        $method = $data['method'] ?? 'app';
        $lines = collect($data['tickets'])->filter(fn ($row) => (int) ($row['quantity'] ?? 0) > 0)->values();
        if ($lines->isEmpty()) {
            return response()->json(['message' => 'Selecciona pelo menos um bilhete.'], 422);
        }

        $livePurchaseError = LiveTicketAccess::validatePurchase($user, $lines->all());
        if ($livePurchaseError !== null) {
            return response()->json(['message' => $livePurchaseError], 422);
        }

        $ticketIds = $lines->pluck('id')->map(fn ($id) => (int) $id)->unique()->values();
        $tickets = Ticket::with(['event', 'formFields'])->whereIn('id', $ticketIds)->get()->keyBy('id');

        $eventId = null;
        $amount = 0.0;
        $prepared = [];

        foreach ($lines as $row) {
            $ticket = $tickets->get((int) $row['id']);
            if (! $ticket) {
                return response()->json(['message' => 'Um dos bilhetes seleccionados não existe.'], 422);
            }

            if ((int) $ticket->is_live !== 1) {
                return response()->json(['message' => 'PayPay só está disponível para bilhetes live neste experimento.'], 422);
            }

            if ($ticket->price_aoa === null || (float) $ticket->price_aoa <= 0) {
                return response()->json(['message' => "O bilhete \"{$ticket->name}\" não tem preço em Kz (price_aoa)."], 422);
            }

            if ($eventId === null) {
                $eventId = (int) $ticket->event_id;
            } elseif ((int) $ticket->event_id !== $eventId) {
                return response()->json(['message' => 'Todos os bilhetes têm de ser do mesmo evento.'], 422);
            }

            $event = $ticket->event;
            if (! $event) {
                return response()->json(['message' => 'Evento não encontrado.'], 404);
            }
            if ($event->isSalesClosed()) {
                return response()->json(['message' => 'As vendas deste evento já terminaram.'], 422);
            }

            $qty = (int) $row['quantity'];
            $status = $ticket->saleStatus(null, $event);
            if ($status !== 'available') {
                return response()->json(['message' => match ($status) {
                    'event_closed' => 'As vendas deste evento já terminaram.',
                    'not_started' => "As vendas do bilhete \"{$ticket->name}\" ainda não começaram.",
                    'expired' => "O período de vendas do bilhete \"{$ticket->name}\" já terminou.",
                    'sold_out' => "O bilhete \"{$ticket->name}\" está esgotado.",
                    default => "O bilhete \"{$ticket->name}\" está indisponível.",
                }], 422);
            }

            $available = $ticket->availableQuantity();
            if ($qty > $available) {
                return response()->json(['message' => "O bilhete \"{$ticket->name}\" não tem quantidade suficiente. Disponível: {$available}."], 422);
            }

            $perOrder = $ticket->maxQuantityPerOrder();
            if ($qty > $perOrder) {
                return response()->json(['message' => "Só podes comprar até {$perOrder} bilhete(s) de \"{$ticket->name}\" por compra."], 422);
            }

            $answersError = $this->validateFormAnswers($ticket, $qty, $row['answers'] ?? null);
            if ($answersError) {
                return response()->json(['message' => $answersError], 422);
            }

            $unit = round((float) $ticket->price_aoa, 2);
            $lineTotal = round($unit * $qty, 2);
            $amount += $lineTotal;

            $prepared[] = [
                'ticket' => $ticket,
                'qty' => $qty,
                'price' => $unit,
                'total' => $lineTotal,
                'form_answers' => is_array($row['answers'] ?? null) ? array_values($row['answers']) : null,
            ];
        }

        $amountStr = number_format($amount, 2, '.', '');
        $phone = isset($data['phone']) ? preg_replace('/\D+/', '', (string) $data['phone']) : null;
        $phone = $phone !== '' ? $phone : null;

        $event = Event::find($eventId);
        $subject = mb_substr('Live - '.($event?->name ?? 'MTicket'), 0, 256);

        $order = DB::transaction(function () use ($user, $eventId, $amountStr, $method, $phone, $data, $request, $prepared) {
            $order = PayPayOrder::create([
                'user_id' => $user->id,
                'event_id' => $eventId,
                'customer_name' => $user->name,
                'customer_email' => $user->email,
                'customer_mobile' => $data['customer_mobile'] ?? $user->mobile ?? null,
                'amount' => $amountStr,
                'currency' => 'AOA',
                'pay_method' => $method,
                'status' => 'pending',
                'phone' => $phone,
                'payer_ip' => $request->ip(),
            ]);

            foreach ($prepared as $line) {
                PayPayOrderItem::create([
                    'paypay_order_id' => $order->id,
                    'ticket_id' => $line['ticket']->id,
                    'qty' => $line['qty'],
                    'price' => $line['price'],
                    'total' => $line['total'],
                    'form_answers' => $line['form_answers'],
                ]);
            }

            return $order->fresh('items');
        });

        try {
            $payment = $this->paypay->initiate(
                amountAoa: $amountStr,
                subject: $subject,
                method: $method,
                phone: $phone,
                payerName: $data['payer_name'] ?? $user->name,
                payerIp: $request->ip(),
                payable: $order,
            );
        } catch (PayPayException $e) {
            if ($e->definitive) {
                $order->update(['status' => 'failed']);
            }

            return response()->json([
                'message' => $e->definitive
                    ? 'A PayPay recusou o pagamento: '.$e->getMessage()
                    : 'Não foi possível contactar a PayPay. Tenta novamente.',
                'order_id' => $order->id,
            ], $e->definitive ? 422 : 502);
        }

        return response()->json($this->payload($order->fresh('items'), $payment), 201);
    }

    public function show(string $id): JsonResponse
    {
        $user = Auth::user();
        $order = PayPayOrder::with(['items.ticket:id,name,is_live', 'payment', 'event:id,name,slug'])
            ->where('user_id', $user->id)
            ->find($id);

        if (! $order) {
            return response()->json(['message' => 'Encomenda não encontrada.'], 404);
        }

        return response()->json($this->payload($order, $order->payment));
    }

    private function payload(PayPayOrder $order, $payment): array
    {
        $checkout = $payment ? $this->paypay->checkoutPayload($payment) : null;

        return [
            'order' => [
                'id' => $order->id,
                'event_id' => $order->event_id,
                'event' => $order->relationLoaded('event') ? $order->event : null,
                'amount' => (float) $order->amount,
                'currency' => $order->currency,
                'status' => $order->status,
                'pay_method' => $order->pay_method,
                'fulfilled_at' => $order->fulfilled_at?->toIso8601String(),
                'created_at' => $order->created_at?->toIso8601String(),
                'items' => $order->items->map(fn (PayPayOrderItem $item) => [
                    'ticket_id' => $item->ticket_id,
                    'name' => $item->ticket->name ?? null,
                    'qty' => (int) $item->qty,
                    'price' => (float) $item->price,
                    'total' => (float) $item->total,
                ])->values(),
            ],
            'payment' => $payment ? [
                'out_trade_no' => $payment->out_trade_no,
                'trade_no' => $payment->trade_no,
                'status' => $payment->status,
                'amount' => (float) $payment->amount,
                'currency' => $payment->currency,
                'paid_at' => $payment->paid_at?->toIso8601String(),
                'expires_at' => $payment->expires_at?->toIso8601String(),
            ] : null,
            'checkout' => $checkout,
            'can_watch_live' => $order->isFulfilled(),
        ];
    }

    private function validateFormAnswers(Ticket $ticket, int $qty, mixed $answersList): ?string
    {
        if ($ticket->formFields->isEmpty()) {
            return null;
        }

        if (! is_array($answersList) || count($answersList) < $qty) {
            return "Preenche o formulário de \"{$ticket->name}\" para cada bilhete.";
        }

        for ($i = 0; $i < $qty; $i++) {
            $unit = $answersList[$i] ?? [];
            if (! is_array($unit)) {
                return "Respostas inválidas para \"{$ticket->name}\" (participante ".($i + 1).').';
            }

            foreach ($ticket->formFields as $field) {
                if (! $field->required) {
                    continue;
                }

                $value = $unit[$field->field_key] ?? null;
                $missing = in_array($field->type, ['terms', 'checkbox'], true)
                    ? ! filter_var($value, FILTER_VALIDATE_BOOLEAN) && $value !== 1 && $value !== '1'
                    : ($value === null || $value === '');

                if ($missing) {
                    return "Campo obrigatório \"{$field->label}\" em \"{$ticket->name}\" (participante ".($i + 1).').';
                }
            }
        }

        return null;
    }
}
