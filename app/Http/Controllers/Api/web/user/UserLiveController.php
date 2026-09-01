<?php

namespace App\Http\Controllers\Api\web\user;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Services\MuxService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use RuntimeException;

class UserLiveController extends Controller
{
    public function __construct(protected MuxService $mux)
    {
    }

    public function show(string $id): JsonResponse
    {
        $event = $this->findEvent($id);

        if (! $event) {
            return response()->json(['message' => 'Evento não encontrado.'], 404);
        }

        if (! $event->live) {
            return response()->json([
                'live' => null,
            ]);
        }

        $live = $this->mux->syncLiveStatus($event->live);

        return response()->json([
            'live' => $live->toPublicArray(),
        ]);
    }

    public function playback(string $id): JsonResponse
    {
        $event = $this->findEvent($id);

        if (! $event) {
            return response()->json(['message' => 'Evento não encontrado.'], 404);
        }

        if (! $event->live) {
            return response()->json(['message' => 'Este evento ainda não tem live.'], 404);
        }

        $user = Auth::user();

        if (! $event->canWatchLive($user)) {
            return response()->json([
                'message' => 'Precisas de um bilhete de live para ver esta transmissão.',
            ], 403);
        }

        $live = $this->mux->syncLiveStatus($event->live);

        try {
            $token = $this->mux->generatePlaybackToken($live->playback_id);
        } catch (RuntimeException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 502);
        }

        return response()->json([
            'status' => $live->status,
            'active' => $live->isActive(),
            'url' => $this->mux->playbackUrl($live->playback_id, $token),
        ]);
    }

    private function findEvent(string $id): ?Event
    {
        return Event::with('live')
            ->where(function ($query) use ($id) {
                $query->where('slug', $id)->orWhere('id', $id);
            })
            ->first();
    }
}
