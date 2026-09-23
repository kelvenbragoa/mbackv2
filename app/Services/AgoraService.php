<?php

namespace App\Services;

use App\Models\Event;
use App\Support\Agora\RtcTokenBuilder;
use RuntimeException;

class AgoraService
{
    public const HOST_UID = 1;
    public const GUEST_UID_OFFSET = 1000000;

    public function appId(): string
    {
        return (string) config('services.agora.app_id');
    }

    public function ensureConfigured(): void
    {
        if ($this->appId() === '' || (string) config('services.agora.app_certificate') === '') {
            throw new RuntimeException('Agora não está configurado no servidor (AGORA_APP_ID / AGORA_APP_CERTIFICATE).');
        }
    }

    public function channelFor(Event $event): string
    {
        return 'mticket_event_'.$event->id;
    }

    public function guestUid(int $userId): int
    {
        return self::GUEST_UID_OFFSET + $userId;
    }

    public function hostCredentials(string $channel): array
    {
        return $this->credentials($channel, self::HOST_UID, RtcTokenBuilder::ROLE_PUBLISHER, 'host');
    }

    public function audienceCredentials(string $channel): array
    {
        return $this->credentials($channel, 0, RtcTokenBuilder::ROLE_SUBSCRIBER, 'audience');
    }

    public function guestCredentials(string $channel, int $uid): array
    {
        return $this->credentials($channel, $uid, RtcTokenBuilder::ROLE_PUBLISHER, 'guest');
    }

    protected function credentials(string $channel, int $uid, int $role, string $roleName): array
    {
        $this->ensureConfigured();

        $ttl = max(600, (int) config('services.agora.token_ttl', 7200));

        return [
            'app_id' => $this->appId(),
            'channel' => $channel,
            'uid' => $uid,
            'role' => $roleName,
            'host_uid' => self::HOST_UID,
            'token' => RtcTokenBuilder::buildTokenWithUid(
                $this->appId(),
                (string) config('services.agora.app_certificate'),
                $channel,
                $uid,
                $role,
                $ttl
            ),
            'expires_in' => $ttl,
        ];
    }
}
