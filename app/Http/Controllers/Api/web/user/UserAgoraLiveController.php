<?php

namespace App\Http\Controllers\Api\web\user;

use App\Events\LiveBroadcast;
use App\Http\Controllers\Controller;
use App\Models\AgoraLiveGuest;
use App\Models\Event;
use App\Services\AgoraService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use RuntimeException;

class UserAgoraLiveController extends Controller
{
    public function __construct(protected AgoraService $agora)
    {
    }

    public function show(string $id): JsonResponse
    {
        $event = $this->findEvent($id);

        if (! $event) {
            return response()->json(['message' => 'Evento não encontrado.'], 404);
        }

        return response()->json([
            'live' => $event->agoraLive?->toPublicArray(),
        ]);
    }

    public function join(string $id): JsonResponse
    {
        [$event, $error] = $this->resolveWatchable($id);

        if ($error) {
            return $error;
        }

        $live = $event->agoraLive;

        try {
            $credentials = $this->agora->audienceCredentials($live->channel);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 502);
        }

        $guest = $this->openGuest($live->id);

        return response()->json([
            'live' => $live->toPublicArray(),
            'credentials' => $credentials,
            'guest' => $this->guestPayload($live->channel, $guest),
        ]);
    }

    public function guest(string $id): JsonResponse
    {
        [$event, $error] = $this->resolveWatchable($id);

        if ($error) {
            return $error;
        }

        $live = $event->agoraLive;
        $guest = $this->latestGuest($live->id);

        return response()->json([
            'live' => $live->toPublicArray(),
            'guest' => $this->guestPayload($live->channel, $guest),
        ]);
    }

    /**
     * Viewers ping this while watching; it feeds the "watching now", peak and unique counters.
     */
    public function presence(string $id): JsonResponse
    {
        [$event, $error] = $this->resolveWatchable($id);

        if ($error) {
            return $error;
        }

        $session = $event->agoraLive->liveSession();

        if (! $session) {
            return response()->json(['viewers' => 0]);
        }

        if ((int) $event->user_id === (int) Auth::id()) {
            return response()->json(['viewers' => $session->currentViewers()]);
        }

        return response()->json(['viewers' => $session->touchViewer((int) Auth::id())]);
    }

    public function requestGuest(string $id): JsonResponse
    {
        [$event, $error] = $this->resolveWatchable($id);

        if ($error) {
            return $error;
        }

        $live = $event->agoraLive;

        if (! $live->isActive()) {
            return response()->json(['message' => 'Só podes pedir para participar quando a live estiver ao vivo.'], 422);
        }

        if ((int) $event->user_id === (int) Auth::id()) {
            return response()->json(['message' => 'És o anfitrião desta live.'], 422);
        }

        $guest = $this->openGuest($live->id);

        if (! $guest) {
            $guest = AgoraLiveGuest::create([
                'agora_live_id' => $live->id,
                'user_id' => Auth::id(),
                'uid' => $this->agora->guestUid((int) Auth::id()),
                'status' => AgoraLiveGuest::STATUS_PENDING,
            ]);
            LiveBroadcast::toHost($event->id, 'guests.changed');
        }

        return response()->json([
            'message' => 'Pedido enviado. Aguarda que o anfitrião aceite.',
            'live' => $live->toPublicArray(),
            'guest' => $this->guestPayload($live->channel, $guest),
        ], 201);
    }

    public function leaveGuest(string $id): JsonResponse
    {
        $event = $this->findEvent($id);

        if (! $event || ! $event->agoraLive) {
            return response()->json(['message' => 'Este evento não tem live interativa.'], 404);
        }

        $live = $event->agoraLive;
        $guest = $this->openGuest($live->id);

        if ($guest) {
            $guest->update([
                'status' => $guest->status === AgoraLiveGuest::STATUS_PENDING
                    ? AgoraLiveGuest::STATUS_CANCELLED
                    : AgoraLiveGuest::STATUS_LEFT,
            ]);
            LiveBroadcast::toHost($event->id, 'guests.changed');
        }

        return response()->json([
            'live' => $live->toPublicArray(),
            'guest' => $this->guestPayload($live->channel, $guest?->fresh()),
        ]);
    }

    /**
     * @return array{0: ?Event, 1: ?JsonResponse}
     */
    private function resolveWatchable(string $id): array
    {
        $event = $this->findEvent($id);

        if (! $event) {
            return [null, response()->json(['message' => 'Evento não encontrado.'], 404)];
        }

        if (! $event->agoraLive || $event->agoraLive->isDisabled()) {
            return [null, response()->json(['message' => 'Este evento não tem live interativa.'], 404)];
        }

        if (! $event->canWatchLive(Auth::user())) {
            return [null, response()->json([
                'message' => 'Precisas de um bilhete de live para ver esta transmissão.',
            ], 403)];
        }

        return [$event, null];
    }

    private function openGuest(int $liveId): ?AgoraLiveGuest
    {
        return AgoraLiveGuest::where('agora_live_id', $liveId)
            ->where('user_id', Auth::id())
            ->whereIn('status', AgoraLiveGuest::OPEN_STATUSES)
            ->latest('id')
            ->first();
    }

    private function latestGuest(int $liveId): ?AgoraLiveGuest
    {
        return AgoraLiveGuest::where('agora_live_id', $liveId)
            ->where('user_id', Auth::id())
            ->latest('id')
            ->first();
    }

    private function guestPayload(string $channel, ?AgoraLiveGuest $guest): ?array
    {
        if (! $guest) {
            return null;
        }

        $payload = $guest->toArrayForGuest();

        if ($guest->status === AgoraLiveGuest::STATUS_ACCEPTED) {
            try {
                $payload['credentials'] = $this->agora->guestCredentials($channel, $guest->uid);
            } catch (RuntimeException) {
                $payload['credentials'] = null;
            }
        }

        return $payload;
    }

    private function findEvent(string $id): ?Event
    {
        return Event::with('agoraLive')
            ->where(function ($query) use ($id) {
                $query->where('slug', $id)->orWhere('id', $id);
            })
            ->first();
    }
}
