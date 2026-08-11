<?php

namespace App\Http\Traits;

use App\Models\Event;
use Illuminate\Support\Facades\Auth;

trait AuthorizesEventAccess
{
    /**
     * Admins reach every event, promoters only the ones they own.
     * Returns the response to send back when access must be refused, or null when allowed.
     */
    protected function denyEventAccess($eventId)
    {
        $event = Event::find($eventId);

        if (!$event) {
            return response()->json(['message' => 'Evento não encontrado.'], 404);
        }

        $user = Auth::user();

        if (!$user) {
            return response()->json(['message' => 'Sessão inválida.'], 401);
        }

        if ($user->role_id != 1 && $event->user_id != $user->id) {
            return response()->json(['message' => 'Não tens permissão para aceder a este evento.'], 403);
        }

        return null;
    }
}
