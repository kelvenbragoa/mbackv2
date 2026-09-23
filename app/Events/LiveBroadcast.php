<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Realtime message for live screens. Clients listen with the name passed as $name (Echo: ".{$name}").
 */
class LiveBroadcast implements ShouldBroadcastNow
{
    public function __construct(
        public string $channel,
        public string $name,
        public array $payload = [],
    ) {
    }

    public static function toViewers(int $eventId, string $name, array $payload = []): void
    {
        self::send(new self('live.'.$eventId, $name, $payload));
    }

    public static function toHost(int $eventId, string $name, array $payload = []): void
    {
        self::send(new self('live-host.'.$eventId, $name, $payload));
    }

    public static function toUser(int $userId, string $name, array $payload = []): void
    {
        self::send(new self('App.Models.User.'.$userId, $name, $payload));
    }

    /**
     * A websocket outage must never fail the HTTP request that triggered it; clients also poll as fallback.
     */
    private static function send(self $event): void
    {
        try {
            broadcast($event);
        } catch (Throwable $e) {
            Log::warning('Live broadcast failed: '.$e->getMessage(), ['channel' => $event->channel, 'name' => $event->name]);
        }
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel($this->channel)];
    }

    public function broadcastAs(): string
    {
        return $this->name;
    }

    public function broadcastWith(): array
    {
        return $this->payload;
    }
}
