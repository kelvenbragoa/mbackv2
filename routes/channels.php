<?php

use App\Models\Event;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('live.{eventId}', function ($user, $eventId) {
    return (bool) Event::find($eventId)?->canWatchLive($user);
});

Broadcast::channel('live-host.{eventId}', function ($user, $eventId) {
    $event = Event::find($eventId);

    return $event && ((int) $user->role_id === 1 || (int) $event->user_id === (int) $user->id);
});
