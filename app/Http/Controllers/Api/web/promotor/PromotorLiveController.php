<?php

namespace App\Http\Controllers\Api\web\promotor;

use App\Http\Controllers\Controller;
use App\Http\Traits\AuthorizesEventAccess;
use App\Models\Event;
use App\Models\Live;
use App\Services\MuxService;
use Illuminate\Http\JsonResponse;
use RuntimeException;

class PromotorLiveController extends Controller
{
    use AuthorizesEventAccess;

    public function __construct(protected MuxService $mux)
    {
    }

    public function show(string $id): JsonResponse
    {
        if ($denied = $this->denyEventAccess($id)) {
            return $denied;
        }

        $event = Event::with('live')->find($id);

        if (! $event->live) {
            return response()->json([
                'message' => 'Este evento ainda não tem live.',
                'live' => null,
            ], 404);
        }

        return response()->json([
            'live' => $event->live->toPromotorArray(),
        ]);
    }

    public function store(string $id): JsonResponse
    {
        if ($denied = $this->denyEventAccess($id)) {
            return $denied;
        }

        $event = Event::with('live')->find($id);

        if ($event->live) {
            if ($event->live->status === Live::STATUS_DISABLED) {
                try {
                    $this->mux->enableLiveStream($event->live->mux_live_stream_id);
                } catch (RuntimeException $e) {
                    return response()->json([
                        'message' => $e->getMessage(),
                    ], 502);
                }

                $event->live->update(['status' => Live::STATUS_IDLE]);

                return response()->json([
                    'message' => 'Live reactivada. Usa as mesmas credenciais no OBS.',
                    'live' => $event->live->fresh()->toPromotorArray(),
                ]);
            }

            return response()->json([
                'message' => 'A live deste evento já existe.',
                'live' => $event->live->toPromotorArray(),
            ]);
        }

        try {
            $data = $this->mux->createLiveStream((int) $event->id);
        } catch (RuntimeException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 502);
        }

        $live = $event->live()->create($data);

        return response()->json([
            'message' => 'Live criada. Usa o RTMP URL e a stream key no OBS.',
            'live' => $live->toPromotorArray(),
        ], 201);
    }

    public function playback(string $id): JsonResponse
    {
        if ($denied = $this->denyEventAccess($id)) {
            return $denied;
        }

        $event = Event::with('live')->find($id);

        if (! $event->live) {
            return response()->json([
                'message' => 'Este evento ainda não tem live.',
            ], 404);
        }

        try {
            $token = $this->mux->generatePlaybackToken($event->live->playback_id);
        } catch (RuntimeException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 502);
        }

        return response()->json([
            'status' => $event->live->status,
            'active' => $event->live->isActive(),
            'url' => $this->mux->playbackUrl($event->live->playback_id, $token),
        ]);
    }

    public function destroy(string $id): JsonResponse
    {
        if ($denied = $this->denyEventAccess($id)) {
            return $denied;
        }

        $event = Event::with('live')->find($id);

        if (! $event->live) {
            return response()->json([
                'message' => 'Este evento ainda não tem live.',
            ], 404);
        }

        try {
            $this->mux->disableLiveStream($event->live->mux_live_stream_id);
        } catch (RuntimeException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 502);
        }

        $event->live->update(['status' => Live::STATUS_DISABLED]);

        return response()->json([
            'message' => 'Live desactivada.',
            'live' => $event->live->fresh()->toPromotorArray(),
        ]);
    }
}
