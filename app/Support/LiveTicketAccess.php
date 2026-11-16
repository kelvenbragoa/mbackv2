<?php

namespace App\Support;

use App\Models\Event;
use App\Models\SellDetails;
use App\Models\Ticket;
use App\Models\User;
use Carbon\Carbon;

class LiveTicketAccess
{
    public static function accessEndsAtForTicket(Ticket $ticket, ?Carbon $from = null): ?Carbon
    {
        if ((int) $ticket->is_live !== 1) {
            return null;
        }

        $days = (int) ($ticket->valid_for_days ?? 0);
        if ($days <= 0) {
            return null;
        }

        return ($from ?? now())->copy()->addDays($days);
    }

    public static function accessEndsAtForTicketId(int $ticketId, ?Carbon $from = null): ?Carbon
    {
        $ticket = Ticket::find($ticketId);

        return $ticket ? self::accessEndsAtForTicket($ticket, $from) : null;
    }

    public static function maxQuantityPerOrder(Ticket $ticket): int
    {
        if ((int) $ticket->is_live === 1) {
            return 1;
        }

        $perOrder = (int) ($ticket->max_per_order ?? 0);

        return $perOrder > 0 ? $perOrder : 5;
    }

    /**
     * Live purchase rules: qty 1, one live line, no active access already.
     *
     * @param  array<int, array<string, mixed>>  $ticketRows
     */
    public static function validatePurchase(?User $user, array $ticketRows): ?string
    {
        $lines = collect($ticketRows)
            ->filter(fn ($row) => (int) ($row['quantity'] ?? 0) > 0)
            ->values();

        if ($lines->isEmpty()) {
            return null;
        }

        $ids = $lines->pluck('id')->map(fn ($id) => (int) $id)->unique()->filter()->values();
        $tickets = Ticket::whereIn('id', $ids)->get()->keyBy('id');

        $liveLines = [];

        foreach ($lines as $row) {
            $ticket = $tickets->get((int) ($row['id'] ?? 0));
            if (! $ticket || (int) $ticket->is_live !== 1) {
                continue;
            }

            $qty = (int) $row['quantity'];
            if ($qty > 1) {
                return 'Só podes comprar 1 bilhete de live por compra.';
            }

            $liveLines[] = $ticket;
        }

        if ($liveLines === []) {
            return null;
        }

        if (count($liveLines) > 1) {
            return 'Só podes comprar um bilhete de live por compra.';
        }

        if (! $user) {
            return 'Inicia sessão para comprar um bilhete de live.';
        }

        $ticket = $liveLines[0];
        $event = Event::find($ticket->event_id);
        if (! $event) {
            return 'Evento não encontrado.';
        }

        if (! $event->hasLiveTicketFor($user)) {
            return null;
        }

        $ends = $event->liveAccessEndsAtFor($user);
        if ($ends) {
            return 'Já tens acesso à live até '.$ends->timezone(config('app.timezone'))->format('d/m/Y H:i').'.';
        }

        return 'Já tens acesso à live deste evento.';
    }

    /**
     * @return array{active: bool, ends_at: ?string, has_expiry: bool}
     */
    public static function accessPayload(Event $event, ?User $user): array
    {
        if (! $user || ! $event->hasLiveTicketFor($user)) {
            return [
                'active' => false,
                'ends_at' => null,
                'has_expiry' => false,
            ];
        }

        $ends = $event->liveAccessEndsAtFor($user);

        return [
            'active' => true,
            'ends_at' => $ends?->toIso8601String(),
            'has_expiry' => $ends !== null,
        ];
    }

    /**
     * Same payload as accessPayload(), for a page of events in one query.
     *
     * @param  array<int, int>  $eventIds
     * @return array<int, array{active: bool, ends_at: ?string, has_expiry: bool}>
     */
    public static function payloadsFor(int $userId, array $eventIds): array
    {
        $eventIds = array_values(array_unique(array_filter(array_map('intval', $eventIds))));

        if ($userId <= 0 || $eventIds === []) {
            return [];
        }

        $rows = SellDetails::query()
            ->selectRaw('sell_details.event_id, MAX(sell_details.access_ends_at) as ends_at')
            ->whereIn('sell_details.event_id', $eventIds)
            ->where('sell_details.status', '!=', SellDetails::STATUS_RETURNED)
            ->where(function ($query) use ($userId) {
                $query->where('sell_details.user_id', $userId)
                    ->orWhereHas('sell', function ($sell) use ($userId) {
                        $sell->where('user_id', $userId);
                    });
            })
            ->whereHas('ticket', function ($ticket) {
                $ticket->where('is_live', 1);
            })
            ->where(function ($query) {
                $query->whereNull('sell_details.access_ends_at')
                    ->orWhere('sell_details.access_ends_at', '>', now());
            })
            ->groupBy('sell_details.event_id')
            ->get();

        $payloads = [];

        foreach ($rows as $row) {
            $ends = $row->ends_at ? Carbon::parse($row->ends_at)->toIso8601String() : null;
            $payloads[(int) $row->event_id] = [
                'active' => true,
                'ends_at' => $ends,
                'has_expiry' => $ends !== null,
            ];
        }

        return $payloads;
    }
}
