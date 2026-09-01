<?php

namespace App\Services;

use App\Models\Live;
use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Cache;
use MuxPhp\Api\LiveStreamsApi;
use MuxPhp\ApiException;
use MuxPhp\Configuration;
use MuxPhp\Models\CreateAssetRequest;
use MuxPhp\Models\CreateLiveStreamRequest;
use MuxPhp\Models\PlaybackPolicy;
use RuntimeException;

class MuxService
{
    public const RTMP_URL = 'rtmps://global-live.mux.com:443/app';

    protected LiveStreamsApi $liveApi;

    public function __construct()
    {
        $config = Configuration::getDefaultConfiguration()
            ->setUsername((string) config('services.mux.token_id'))
            ->setPassword((string) config('services.mux.token_secret'));

        $this->liveApi = new LiveStreamsApi(new \GuzzleHttp\Client(), $config);
    }

    public function createLiveStream(int $eventId): array
    {
        $request = new CreateLiveStreamRequest([
            'playback_policies' => [PlaybackPolicy::SIGNED],
            'new_asset_settings' => new CreateAssetRequest([
                'playback_policies' => [PlaybackPolicy::SIGNED],
            ]),
            'latency_mode' => 'reduced',
            'passthrough' => (string) $eventId,
        ]);

        try {
            $stream = $this->liveApi->createLiveStream($request)->getData();
        } catch (ApiException $e) {
            throw new RuntimeException('Falha ao criar live stream no Mux: '.$e->getMessage(), $e->getCode(), $e);
        }

        $playbackIds = $stream->getPlaybackIds() ?? [];
        $playback = $playbackIds[0] ?? null;

        if (! $stream->getId() || ! $stream->getStreamKey() || ! $playback) {
            throw new RuntimeException('Mux não devolveu os dados da live stream.');
        }

        return [
            'mux_live_stream_id' => $stream->getId(),
            'stream_key' => $stream->getStreamKey(),
            'playback_id' => $playback->getId(),
            'policy' => 'signed',
        ];
    }

    public function disableLiveStream(string $muxLiveStreamId): void
    {
        try {
            $this->liveApi->disableLiveStream($muxLiveStreamId);
        } catch (ApiException $e) {
            throw new RuntimeException('Falha ao desactivar live stream no Mux: '.$e->getMessage(), $e->getCode(), $e);
        }
    }

    public function enableLiveStream(string $muxLiveStreamId): void
    {
        try {
            $this->liveApi->enableLiveStream($muxLiveStreamId);
        } catch (ApiException $e) {
            throw new RuntimeException('Falha ao reactivar live stream no Mux: '.$e->getMessage(), $e->getCode(), $e);
        }
    }

    public function getLiveStreamStatus(string $muxLiveStreamId): ?string
    {
        try {
            $stream = $this->liveApi->getLiveStream($muxLiveStreamId)->getData();
        } catch (ApiException $e) {
            return null;
        }

        if (! $stream) {
            return null;
        }

        return match (strtolower((string) $stream->getStatus())) {
            'active' => Live::STATUS_ACTIVE,
            'disabled' => Live::STATUS_DISABLED,
            default => Live::STATUS_IDLE,
        };
    }

    public function syncLiveStatus(Live $live): Live
    {
        if ($live->status === Live::STATUS_DISABLED) {
            return $live;
        }

        $cacheKey = 'mux-live-status:'.$live->mux_live_stream_id;
        $status = Cache::remember($cacheKey, 8, function () use ($live) {
            return $this->getLiveStreamStatus($live->mux_live_stream_id);
        });

        if ($status && $status !== $live->status) {
            $live->update(['status' => $status]);
            Cache::forget($cacheKey);
        }

        return $live->fresh() ?? $live;
    }

    public function generatePlaybackToken(string $playbackId, int $ttlHours = 4): string
    {
        $keyId = (string) config('services.mux.signing_key_id');
        $encodedPrivateKey = (string) config('services.mux.signing_key_private');

        if ($keyId === '' || $encodedPrivateKey === '') {
            throw new RuntimeException('Chaves de assinatura Mux não configuradas.');
        }

        $privateKey = base64_decode($encodedPrivateKey, true);

        if ($privateKey === false || $privateKey === '') {
            throw new RuntimeException('Chave privada Mux inválida.');
        }

        return JWT::encode(
            [
                'sub' => $playbackId,
                'aud' => 'v',
                'exp' => now()->addHours($ttlHours)->timestamp,
            ],
            $privateKey,
            'RS256',
            $keyId
        );
    }

    public function playbackUrl(string $playbackId, string $token): string
    {
        return 'https://stream.mux.com/'.$playbackId.'.m3u8?token='.$token;
    }

    public function verifyWebhookSignature(string $rawBody, ?string $signatureHeader): bool
    {
        $secret = (string) config('services.mux.webhook_secret');

        if ($secret === '' || ! $signatureHeader) {
            return false;
        }

        $parts = [];
        foreach (explode(',', $signatureHeader) as $segment) {
            $segment = trim($segment);
            if (! str_contains($segment, '=')) {
                continue;
            }

            [$key, $value] = explode('=', $segment, 2);
            $parts[trim($key)] = trim($value);
        }

        $timestamp = $parts['t'] ?? null;
        $hash = $parts['v1'] ?? null;

        if (! $timestamp || ! $hash || ! ctype_digit($timestamp)) {
            return false;
        }

        if (abs(time() - (int) $timestamp) > 300) {
            return false;
        }

        $expected = hash_hmac('sha256', $timestamp.'.'.$rawBody, $secret);

        return hash_equals($expected, $hash);
    }
}
