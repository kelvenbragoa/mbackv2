<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Live;
use App\Services\MuxService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class MuxWebhookController extends Controller
{
    public function __construct(protected MuxService $mux)
    {
    }

    public function handle(Request $request): Response
    {
        $signature = $request->header('Mux-Signature');

        if (! $this->mux->verifyWebhookSignature($request->getContent(), $signature)) {
            return response('Invalid signature', 401);
        }

        $type = (string) $request->input('type');
        $streamId = (string) $request->input('data.id');

        if ($streamId === '' || ! str_starts_with($type, 'video.live_stream.')) {
            return response()->noContent();
        }

        $live = Live::where('mux_live_stream_id', $streamId)->first();

        if (! $live) {
            Log::info('Mux webhook for unknown live stream', [
                'type' => $type,
                'mux_live_stream_id' => $streamId,
            ]);

            return response()->noContent();
        }

        $status = match ($type) {
            'video.live_stream.active' => Live::STATUS_ACTIVE,
            'video.live_stream.idle' => Live::STATUS_IDLE,
            'video.live_stream.disabled',
            'video.live_stream.deleted' => Live::STATUS_DISABLED,
            default => null,
        };

        if ($status && $live->status !== $status) {
            $live->update(['status' => $status]);
        }

        return response()->noContent();
    }
}
