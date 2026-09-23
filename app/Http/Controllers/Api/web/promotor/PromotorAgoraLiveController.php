<?php

namespace App\Http\Controllers\Api\web\promotor;

use App\Events\LiveBroadcast;
use App\Http\Controllers\Controller;
use App\Http\Traits\AuthorizesEventAccess;
use App\Models\AgoraLive;
use App\Models\AgoraLiveGuest;
use App\Models\Event;
use App\Models\Live;
use App\Services\AgoraService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use RuntimeException;

class PromotorAgoraLiveController extends Controller
{
    use AuthorizesEventAccess;

    public function __construct(protected AgoraService $agora)
    {
    }

    public function show(string $id): JsonResponse
    {
        if ($denied = $this->denyEventAccess($id)) {
            return $denied;
        }

        $live = AgoraLive::where('event_id', $id)->first();

        if (! $live) {
            return response()->json([
                'message' => 'Este evento ainda não tem live interativa.',
                'live' => null,
            ], 404);
        }

        return response()->json($this->payload($live));
    }

    public function store(Request $request, string $id): JsonResponse
    {
        if ($denied = $this->denyEventAccess($id)) {
            return $denied;
        }

        $data = $request->validate([
            'max_guests' => 'nullable|integer|min:1|max:3',
        ]);

        $event = Event::with(['live', 'agoraLive'])->find($id);

        if ($event->live && $event->live->status !== Live::STATUS_DISABLED) {
            return response()->json([
                'message' => 'Este evento já tem uma live broadcast (OBS). Desactiva-a antes de criar uma live interativa.',
            ], 422);
        }

        try {
            $this->agora->ensureConfigured();
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 502);
        }

        $maxGuests = (int) ($data['max_guests'] ?? 1);

        if ($event->agoraLive) {
            $event->agoraLive->update([
                'status' => AgoraLive::STATUS_IDLE,
                'max_guests' => $maxGuests,
            ]);

            return response()->json([
                'message' => 'Live interativa reactivada.',
                ...$this->payload($event->agoraLive->fresh()),
            ]);
        }

        $live = AgoraLive::create([
            'event_id' => $event->id,
            'channel' => $this->agora->channelFor($event),
            'status' => AgoraLive::STATUS_IDLE,
            'max_guests' => $maxGuests,
        ]);

        return response()->json([
            'message' => 'Live interativa criada. Abre o estúdio para começar a transmitir.',
            ...$this->payload($live),
        ], 201);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        if ($denied = $this->denyEventAccess($id)) {
            return $denied;
        }

        $data = $request->validate([
            'max_guests' => 'required|integer|min:1|max:3',
        ]);

        $live = AgoraLive::where('event_id', $id)->firstOrFail();
        $live->update(['max_guests' => $data['max_guests']]);

        return response()->json($this->payload($live->fresh()));
    }

    public function destroy(string $id): JsonResponse
    {
        if ($denied = $this->denyEventAccess($id)) {
            return $denied;
        }

        $live = AgoraLive::where('event_id', $id)->first();

        if (! $live) {
            return response()->json(['message' => 'Este evento ainda não tem live interativa.'], 404);
        }

        $live->closeGuests();
        $live->update([
            'status' => AgoraLive::STATUS_DISABLED,
            'host_seen_at' => null,
        ]);
        $this->broadcastStatus($live);

        return response()->json([
            'message' => 'Live interativa desactivada.',
            ...$this->payload($live->fresh()),
        ]);
    }

    public function hostToken(string $id): JsonResponse
    {
        if ($denied = $this->denyEventAccess($id)) {
            return $denied;
        }

        $live = AgoraLive::where('event_id', $id)->first();

        if (! $live || $live->isDisabled()) {
            return response()->json(['message' => 'A live interativa não está disponível.'], 404);
        }

        try {
            $credentials = $this->agora->hostCredentials($live->channel);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 502);
        }

        return response()->json(['credentials' => $credentials]);
    }

    public function start(string $id): JsonResponse
    {
        if ($denied = $this->denyEventAccess($id)) {
            return $denied;
        }

        $live = AgoraLive::where('event_id', $id)->first();

        if (! $live || $live->isDisabled()) {
            return response()->json(['message' => 'A live interativa não está disponível.'], 404);
        }

        $live->update([
            'status' => AgoraLive::STATUS_ACTIVE,
            'host_user_id' => Auth::id(),
            'host_seen_at' => now(),
            'started_at' => $live->isActive() ? $live->started_at : now(),
        ]);
        $this->broadcastStatus($live);

        return response()->json($this->payload($live->fresh()));
    }

    public function heartbeat(string $id): JsonResponse
    {
        if ($denied = $this->denyEventAccess($id)) {
            return $denied;
        }

        $live = AgoraLive::where('event_id', $id)->firstOrFail();

        if ($live->status === AgoraLive::STATUS_ACTIVE) {
            $live->update(['host_seen_at' => now()]);
        }

        return response()->json($this->payload($live->fresh()));
    }

    public function stop(string $id): JsonResponse
    {
        if ($denied = $this->denyEventAccess($id)) {
            return $denied;
        }

        $live = AgoraLive::where('event_id', $id)->firstOrFail();

        $live->closeGuests();

        if (! $live->isDisabled()) {
            $live->update([
                'status' => AgoraLive::STATUS_IDLE,
                'host_seen_at' => null,
                'started_at' => null,
            ]);
        }
        $this->broadcastStatus($live);

        return response()->json($this->payload($live->fresh()));
    }

    public function acceptGuest(string $id, string $guestId): JsonResponse
    {
        if ($denied = $this->denyEventAccess($id)) {
            return $denied;
        }

        $live = AgoraLive::where('event_id', $id)->firstOrFail();
        $guest = $live->guests()->findOrFail($guestId);

        if ($guest->status !== AgoraLiveGuest::STATUS_PENDING) {
            return response()->json(['message' => 'Este pedido já não está pendente.'], 422);
        }

        if ($live->acceptedGuestsCount() >= $live->max_guests) {
            return response()->json([
                'message' => 'Já atingiste o limite de convidados em directo. Remove um convidado primeiro.',
            ], 422);
        }

        $guest->update([
            'status' => AgoraLiveGuest::STATUS_ACCEPTED,
            'accepted_at' => now(),
        ]);
        $this->notifyGuest($live, $guest);

        return response()->json($this->payload($live->fresh()));
    }

    public function rejectGuest(string $id, string $guestId): JsonResponse
    {
        return $this->closeGuest($id, $guestId, AgoraLiveGuest::STATUS_PENDING, AgoraLiveGuest::STATUS_REJECTED);
    }

    public function removeGuest(string $id, string $guestId): JsonResponse
    {
        return $this->closeGuest($id, $guestId, AgoraLiveGuest::STATUS_ACCEPTED, AgoraLiveGuest::STATUS_REMOVED);
    }

    private function closeGuest(string $id, string $guestId, string $from, string $to): JsonResponse
    {
        if ($denied = $this->denyEventAccess($id)) {
            return $denied;
        }

        $live = AgoraLive::where('event_id', $id)->firstOrFail();
        $guest = $live->guests()->findOrFail($guestId);

        if ($guest->status === $from) {
            $guest->update(['status' => $to]);
            $this->notifyGuest($live, $guest);
        }

        return response()->json($this->payload($live->fresh()));
    }

    private function notifyGuest(AgoraLive $live, AgoraLiveGuest $guest): void
    {
        LiveBroadcast::toUser($guest->user_id, 'guest.updated', [
            'event_id' => $live->event_id,
            'status' => $guest->status,
        ]);
    }

    private function broadcastStatus(AgoraLive $live): void
    {
        $live->refresh();
        LiveBroadcast::toViewers($live->event_id, 'live.status', $live->toPublicArray());
    }

    private function payload(AgoraLive $live): array
    {
        $guests = $live->guests()
            ->with('user:id,name,image')
            ->whereIn('status', AgoraLiveGuest::OPEN_STATUSES)
            ->orderBy('created_at')
            ->get();

        return [
            'live' => $live->toPromotorArray(),
            'host_uid' => AgoraService::HOST_UID,
            'guests' => [
                'pending' => $guests->where('status', AgoraLiveGuest::STATUS_PENDING)->values()->map->toArrayForHost(),
                'accepted' => $guests->where('status', AgoraLiveGuest::STATUS_ACCEPTED)->values()->map->toArrayForHost(),
            ],
        ];
    }
}
