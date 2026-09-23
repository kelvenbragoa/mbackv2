<?php

namespace App\Http\Controllers\Api\web\promotor;

use App\Events\LiveBroadcast;
use App\Http\Controllers\Controller;
use App\Http\Traits\AuthorizesEventAccess;
use App\Models\LiveChatBan;
use App\Models\LiveChatMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class PromotorLiveChatController extends Controller
{
    use AuthorizesEventAccess;

    public function hide(string $id, string $messageId): JsonResponse
    {
        if ($denied = $this->denyEventAccess($id)) {
            return $denied;
        }

        $message = LiveChatMessage::where('event_id', $id)->findOrFail($messageId);
        $wasPinned = $message->pinned_at !== null;

        $message->update(['hidden_at' => now(), 'pinned_at' => null]);

        LiveBroadcast::toViewers((int) $id, 'chat.hidden', ['ids' => [$message->id]]);

        if ($wasPinned) {
            LiveBroadcast::toViewers((int) $id, 'chat.pinned', ['message' => null]);
        }

        return response()->json(['ok' => true]);
    }

    public function pin(string $id, string $messageId): JsonResponse
    {
        if ($denied = $this->denyEventAccess($id)) {
            return $denied;
        }

        $message = LiveChatMessage::with('user:id,name,image')
            ->where('event_id', $id)
            ->visible()
            ->findOrFail($messageId);

        LiveChatMessage::where('event_id', $id)->whereNotNull('pinned_at')->update(['pinned_at' => null]);
        $message->update(['pinned_at' => now()]);

        $payload = $message->fresh('user')->toChatArray();
        LiveBroadcast::toViewers((int) $id, 'chat.pinned', ['message' => $payload]);

        return response()->json(['pinned' => $payload]);
    }

    public function unpin(string $id): JsonResponse
    {
        if ($denied = $this->denyEventAccess($id)) {
            return $denied;
        }

        LiveChatMessage::where('event_id', $id)->whereNotNull('pinned_at')->update(['pinned_at' => null]);
        LiveBroadcast::toViewers((int) $id, 'chat.pinned', ['message' => null]);

        return response()->json(['pinned' => null]);
    }

    public function ban(string $id, string $userId): JsonResponse
    {
        if ($denied = $this->denyEventAccess($id)) {
            return $denied;
        }

        LiveChatBan::firstOrCreate(
            ['event_id' => $id, 'user_id' => $userId],
            ['banned_by' => Auth::id()]
        );

        $hiddenIds = LiveChatMessage::where('event_id', $id)
            ->where('user_id', $userId)
            ->visible()
            ->pluck('id')
            ->all();

        if ($hiddenIds) {
            $hadPinned = LiveChatMessage::whereIn('id', $hiddenIds)->whereNotNull('pinned_at')->exists();
            LiveChatMessage::whereIn('id', $hiddenIds)->update(['hidden_at' => now(), 'pinned_at' => null]);
            LiveBroadcast::toViewers((int) $id, 'chat.hidden', ['ids' => $hiddenIds]);

            if ($hadPinned) {
                LiveBroadcast::toViewers((int) $id, 'chat.pinned', ['message' => null]);
            }
        }

        return response()->json(['ok' => true, 'hidden' => count($hiddenIds)]);
    }
}
