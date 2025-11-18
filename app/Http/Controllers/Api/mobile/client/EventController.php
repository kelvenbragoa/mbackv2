<?php

namespace App\Http\Controllers\Api\Mobile\Client;

use App\Models\Event;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class EventController extends BaseController
{
    /**
     * Get featured events for home screen.
     */
    public function featured(Request $request): JsonResponse
    {
        try {
            $limit = min($request->get('limit', 10), 50);
            $categoryId = $request->get('category_id');

            $query = Event::with([
                'category', 'city', 'province', 'tickets', 'sells', 'review', 'like',
            ])->where('status_id', 2); // Assumindo que status 1 é ativo

            if ($categoryId) {
                $query->where('main_category_id', $categoryId);
            }

            // Ordernar por data mais próxima
            // $query->where('start_date', '>=', now()->format('Y-m-d'))
            //       ->orderBy('start_date', 'asc');
            $query->orderBy('id', 'desc');

            $events = $query->paginate($limit);
            

            $formattedEvents = $events->getCollection()->map(function ($event) use ($request) {
                return $this->formatEvent($event);
            });

            $events->setCollection($formattedEvents);

            return $this->sendResponse(
                ['events' => $events->items()],
                'Eventos em destaque recuperados com sucesso',
                $this->formatPagination($events)
            );

        } catch (\Exception $e) {
            return $this->sendError('Erro ao buscar eventos em destaque', [], 500);
        }
    }

    /**
     * Get upcoming events based on user location.
     */
    public function upcoming(Request $request): JsonResponse
    {
        try {
            $lat = $request->get('lat');
            $lng = $request->get('lng');
            $radius = $request->get('radius', 25);
            $limit = min($request->get('limit', 20), 100);

            $query = Event::with([
                'category', 'city', 'province', 'tickets', 'sells', 'review', 'like'
            ])->where('status_id', 1)
              ->where('start_date', '>=', now()->format('Y-m-d'));

            // Se tiver coordenadas, filtrar por cidade (simplificação)
            if ($lat && $lng) {
                // Por enquanto, vamos filtrar por cidade do usuário
                // Em uma implementação completa, usaria cálculo de distância
                $userCity = Auth::user()?->city_id;
                if ($userCity) {
                    $query->where('city_id', $userCity);
                }
            }

            $events = $query->orderBy('start_date', 'asc')->paginate($limit);

            $formattedEvents = $events->getCollection()->map(function ($event) {
                return $this->formatEvent($event, Auth::id());
            });

            $events->setCollection($formattedEvents);

            return $this->sendResponse(
                ['events' => $events->items()],
                'Próximos eventos recuperados com sucesso',
                $this->formatPagination($events)
            );

        } catch (\Exception $e) {
            return $this->sendError('Erro ao buscar próximos eventos', [], 500);
        }
    }

    /**
     * Search events with filters.
     */
    public function search(Request $request): JsonResponse
    {
        try {
            $query = Event::with([
                'category', 'city', 'province', 'tickets', 'sells', 'review', 'like'
            ])->where('status_id', 1);

            // Filtro por texto
            if ($search = $request->get('q')) {
                $query->where(function (Builder $q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('description', 'like', "%{$search}%");
                });
            }

            // Filtro por categoria
            if ($categoryId = $request->get('category_id')) {
                $query->where('main_category_id', $categoryId);
            }

            // Filtro por cidade
            if ($city = $request->get('city')) {
                $query->whereHas('city', function (Builder $q) use ($city) {
                    $q->where('name', 'like', "%{$city}%");
                });
            }

            // Filtro por estado/província
            if ($state = $request->get('state')) {
                $query->whereHas('province', function (Builder $q) use ($state) {
                    $q->where('name', 'like', "%{$state}%");
                });
            }

            // Filtro por data
            if ($dateFrom = $request->get('date_from')) {
                $query->where('start_date', '>=', $dateFrom);
            }

            if ($dateTo = $request->get('date_to')) {
                $query->where('start_date', '<=', $dateTo);
            }

            // Filtro por preço
            if ($priceMin = $request->get('price_min')) {
                $query->whereHas('tickets', function (Builder $q) use ($priceMin) {
                    $q->where('price', '>=', $priceMin);
                });
            }

            if ($priceMax = $request->get('price_max')) {
                $query->whereHas('tickets', function (Builder $q) use ($priceMax) {
                    $q->where('price', '<=', $priceMax);
                });
            }

            // Ordenação
            $sortBy = $request->get('sort_by', 'date');
            $sortOrder = $request->get('sort_order', 'asc');

            switch ($sortBy) {
                case 'date':
                    $query->orderBy('start_date', $sortOrder);
                    break;
                case 'price':
                    $query->join('tickets', 'events.id', '=', 'tickets.event_id')
                          ->orderBy('tickets.price', $sortOrder);
                    break;
                case 'popularity':
                    $query->withCount('like')->orderBy('like_count', $sortOrder);
                    break;
                default:
                    $query->orderBy('start_date', 'asc');
            }

            $perPage = min($request->get('per_page', 20), 100);
            $events = $query->paginate($perPage);

            $formattedEvents = $events->getCollection()->map(function ($event) {
                return $this->formatEvent($event, Auth::id());
            });

            $events->setCollection($formattedEvents);

            return $this->sendResponse(
                ['events' => $events->items()],
                'Busca de eventos realizada com sucesso',
                $this->formatPagination($events)
            );

        } catch (\Exception $e) {
            return $this->sendError('Erro ao buscar eventos', [], 500);
        }
    }

    /**
     * Get search suggestions.
     */
    public function suggestions(Request $request): JsonResponse
    {
        try {
            $search = $request->get('q', '');
            $suggestions = [];

            if (strlen($search) >= 2) {
                // Sugestões de eventos
                $events = Event::where('name', 'like', "%{$search}%")
                               ->where('status_id', 1)
                               ->limit(3)
                               ->get(['id', 'name']);

                foreach ($events as $event) {
                    $suggestions[] = [
                        'type' => 'event',
                        'text' => $event->name,
                        'id' => $event->id
                    ];
                }

                // Sugestões de categorias
                $categories = \App\Models\Category::where('name', 'like', "%{$search}%")
                                                 ->limit(2)
                                                 ->get(['id', 'name']);

                foreach ($categories as $category) {
                    $suggestions[] = [
                        'type' => 'category',
                        'text' => $category->name,
                        'id' => $category->id
                    ];
                }

                // Sugestões de locais
                $cities = \App\Models\City::where('name', 'like', "%{$search}%")
                                         ->limit(2)
                                         ->get(['id', 'name']);

                foreach ($cities as $city) {
                    $suggestions[] = [
                        'type' => 'venue',
                        'text' => $city->name,
                        'id' => $city->id
                    ];
                }
            }

            return $this->sendResponse(
                ['suggestions' => $suggestions],
                'Sugestões recuperadas com sucesso'
            );

        } catch (\Exception $e) {
            return $this->sendError('Erro ao buscar sugestões', [], 500);
        }
    }

    /**
     * Get popular searches.
     */
    public function popularSearches(): JsonResponse
    {
        try {
            // Para implementação futura, pode usar uma tabela de logs de pesquisa
            // Por enquanto, retornar algumas categorias populares
            $popularSearches = [
                ['term' => 'música', 'count' => 1250, 'trending' => true],
                ['term' => 'festa', 'count' => 890, 'trending' => false],
                ['term' => 'concerto', 'count' => 650, 'trending' => true],
                ['term' => 'show', 'count' => 580, 'trending' => false],
                ['term' => 'teatro', 'count' => 420, 'trending' => false]
            ];

            return $this->sendResponse(
                ['popular_searches' => $popularSearches],
                'Buscas populares recuperadas com sucesso'
            );

        } catch (\Exception $e) {
            return $this->sendError('Erro ao buscar buscas populares', [], 500);
        }
    }

    /**
     * Get event details by ID.
     */
    public function show(Request $request, $id): JsonResponse
    {
        try {
            $event = Event::with([
                'category', 'city', 'province', 'tickets', 'sells', 'review', 'like', 'lineups'
            ])->find($id);

            if (!$event) {
                return $this->sendError('Evento não encontrado', [], 404);
            }

            $formattedEvent = $this->formatEvent($event, Auth::id());

            return $this->sendResponse(
                ['event' => $formattedEvent],
                'Detalhes do evento recuperados com sucesso'
            );

        } catch (\Exception $e) {
            return $this->sendError('Erro ao buscar detalhes do evento', [], 500);
        }
    }
}