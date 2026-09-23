<?php

namespace App\Http\Controllers\Api\web\user;

use App\Events\LiveBroadcast;
use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\LiveChatBan;
use App\Models\LiveChatMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;

class LiveChatController extends Controller
{
    private const HISTORY_SIZE = 50;

    public function index(string $id): JsonResponse
    {
        [$event, $error] = $this->resolve($id);

        if ($error) {
            return $error;
        }

        $messages = LiveChatMessage::with('user:id,name,image')
            ->where('event_id', $event->id)
            ->visible()
            ->latest('id')
            ->limit(self::HISTORY_SIZE)
            ->get()
            ->reverse()
            ->values();

        $pinned = LiveChatMessage::with('user:id,name,image')
            ->where('event_id', $event->id)
            ->visible()
            ->whereNotNull('pinned_at')
            ->latest('pinned_at')
            ->first();

        return response()->json([
            'event_id' => $event->id,
            'is_host' => $this->isHost($event),
            'banned' => $this->isBanned($event),
            'messages' => $messages->map->toChatArray(),
            'pinned' => $pinned?->toChatArray(),
        ]);
    }

    public function store(Request $request, string $id): JsonResponse
    {
        [$event, $error] = $this->resolve($id);

        if ($error) {
            return $error;
        }

        $data = $request->validate([
            'body' => 'required|string|max:300',
        ]);

        $body = trim(preg_replace('/\s+/u', ' ', $data['body']));

        if ($body === '') {
            return response()->json(['message' => 'A mensagem está vazia.'], 422);
        }

        if ($this->isBanned($event)) {
            return response()->json(['message' => 'Não podes comentar nesta live.'], 403);
        }

        $key = 'live-chat:'.$event->id.':'.Auth::id();

        if (RateLimiter::tooManyAttempts($key, 1)) {
            return response()->json(['message' => 'Estás a enviar mensagens muito depressa.'], 429);
        }

        RateLimiter::hit($key, 1);

        $message = LiveChatMessage::create([
            'event_id' => $event->id,
            'user_id' => Auth::id(),
            'body' => $body,
            'is_host' => $this->isHost($event),
        ])->load('user:id,name,image');

        $payload = $message->toChatArray();
        LiveBroadcast::toViewers($event->id, 'chat.message', ['message' => $payload]);

        return response()->json(['message' => $payload], 201);
    }

    public function react(Request $request, string $id): JsonResponse
    {
        [$event, $error] = $this->resolve($id);

        if ($error) {
            return $error;
        }

        $data = $request->validate([
            'count' => 'nullable|integer|min:1|max:30',
        ]);

        LiveBroadcast::toViewers($event->id, 'reaction', [
            'count' => (int) ($data['count'] ?? 1),
            'user_id' => Auth::id(),
        ]);

        return response()->json(['ok' => true]);
    }

    /**
     * @return array{0: ?Event, 1: ?JsonResponse}
     */
    private function resolve(string $id): array
    {
        $event = Event::where(function ($query) use ($id) {
            $query->where('slug', $id)->orWhere('id', $id);
        })->first();

        if (! $event) {
            return [null, response()->json(['message' => 'Evento não encontrado.'], 404)];
        }

        if (! $event->canWatchLive(Auth::user())) {
            return [null, response()->json(['message' => 'Precisas de um bilhete de live para participar no chat.'], 403)];
        }

        return [$event, null];
    }

    private function isHost(Event $event): bool
    {
        $user = Auth::user();

        return (int) $user->role_id === 1 || (int) $event->user_id === (int) $user->id;
    }

    private function isBanned(Event $event): bool
    {
        return LiveChatBan::where('event_id', $event->id)->where('user_id', Auth::id())->exists();
    }
}
