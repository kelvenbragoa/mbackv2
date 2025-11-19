<?php

namespace App\Http\Controllers\Api\mobile\client;

use App\Models\Event;
use App\Models\LikeEvent;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class FavoriteController extends BaseController
{
    /**
     * List user's favorite events.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if (!$user) {
                return $this->sendError('Usuário não autenticado', [], 401);
            }

            $perPage = min($request->get('per_page', 20), 100);
            
            $favoriteEventIds = LikeEvent::where('user_id', $user->id)
                                        ->pluck('event_id');

            $events = Event::with([
                'category', 'city', 'province', 'tickets', 'sells', 'review', 'like'
            ])->whereIn('id', $favoriteEventIds)
              ->where('status_id', 1)
              ->orderBy('created_at', 'desc')
              ->paginate($perPage);

            $formattedEvents = $events->getCollection()->map(function ($event) use ($user) {
                return $this->formatEvent($event, $user->id);
            });

            $events->setCollection($formattedEvents);

            return $this->sendResponse(
                ['events' => $events->items()],
                'Eventos favoritos recuperados com sucesso',
                $this->formatPagination($events)
            );

        } catch (\Exception $e) {
            return $this->sendError('Erro ao buscar eventos favoritos', [], 500);
        }
    }

    /**
     * Add event to favorites.
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if (!$user) {
                return $this->sendError('Usuário não autenticado', [], 401);
            }

            $request->validate([
                'event_id' => 'required|integer|exists:events,id'
            ]);

            $eventId = $request->get('event_id');

            // Verificar se o evento existe e está ativo
            $event = Event::where('id', $eventId)
                          ->where('status_id', 1)
                          ->first();

            if (!$event) {
                return $this->sendError('Evento não encontrado ou não está ativo', [], 404);
            }

            // Verificar se já está nos favoritos
            $existingFavorite = LikeEvent::where('user_id', $user->id)
                                        ->where('event_id', $eventId)
                                        ->first();

            if ($existingFavorite) {
                return $this->sendError('Evento já está nos favoritos', [], 409);
            }

            // Criar favorito
            $favorite = LikeEvent::create([
                'user_id' => $user->id,
                'event_id' => $eventId
            ]);

            return $this->sendResponse(
                [
                    'favorite' => [
                        'id' => $favorite->id,
                        'event_id' => $favorite->event_id,
                        'user_id' => $favorite->user_id,
                        'created_at' => $favorite->created_at->toISOString()
                    ]
                ],
                'Evento adicionado aos favoritos com sucesso',
                null
            );

        } catch (ValidationException $e) {
            return $this->sendValidationError($e->errors());
        } catch (\Exception $e) {
            return $this->sendError('Erro ao adicionar evento aos favoritos', [], 500);
        }
    }

    /**
     * Remove event from favorites.
     */
    public function destroy(Request $request, $eventId): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if (!$user) {
                return $this->sendError('Usuário não autenticado', [], 401);
            }

            // Buscar e remover o favorito
            $favorite = LikeEvent::where('user_id', $user->id)
                                ->where('event_id', $eventId)
                                ->first();

            if (!$favorite) {
                return $this->sendError('Evento não está nos favoritos', [], 404);
            }

            $favorite->delete();

            return $this->sendResponse(
                [],
                'Evento removido dos favoritos com sucesso'
            );

        } catch (\Exception $e) {
            return $this->sendError('Erro ao remover evento dos favoritos', [], 500);
        }
    }

    /**
     * Check if events are in user's favorites.
     */
    public function check(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if (!$user) {
                return $this->sendError('Usuário não autenticado', [], 401);
            }

            $eventIds = $request->get('event_ids');
            
            if (!$eventIds) {
                return $this->sendValidationError(['event_ids' => ['O campo event_ids é obrigatório']]);
            }

            // Converter string para array se necessário
            if (is_string($eventIds)) {
                $eventIds = explode(',', $eventIds);
            }

            // Validar que são números
            $eventIds = array_filter($eventIds, 'is_numeric');

            if (empty($eventIds)) {
                return $this->sendValidationError(['event_ids' => ['IDs de eventos inválidos']]);
            }

            // Buscar favoritos do usuário para estes eventos
            $favorites = LikeEvent::where('user_id', $user->id)
                                 ->whereIn('event_id', $eventIds)
                                 ->pluck('event_id')
                                 ->toArray();

            // Montar resposta
            $result = [];
            foreach ($eventIds as $eventId) {
                $result[(string)$eventId] = in_array($eventId, $favorites);
            }

            return $this->sendResponse(
                ['favorites' => $result],
                'Status dos favoritos verificado com sucesso'
            );

        } catch (\Exception $e) {
            return $this->sendError('Erro ao verificar status dos favoritos', [], 500);
        }
    }

    /**
     * Get favorite events count.
     */
    public function count(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if (!$user) {
                return $this->sendError('Usuário não autenticado', [], 401);
            }

            $count = LikeEvent::where('user_id', $user->id)->count();

            return $this->sendResponse(
                ['count' => $count],
                'Contagem de favoritos recuperada com sucesso'
            );

        } catch (\Exception $e) {
            return $this->sendError('Erro ao contar favoritos', [], 500);
        }
    }
}