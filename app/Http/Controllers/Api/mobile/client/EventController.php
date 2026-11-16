<?php

namespace App\Http\Controllers\Api\mobile\client;

use App\Models\Event;
use App\Models\FavoriteEvent;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class EventController extends BaseController
{
    /** Home lists are the same for everyone except favorite and live access. */
    private const CATALOG_CACHE_SECONDS = 30;

    /**
     * Get featured events for home screen.
     */
    public function featured(Request $request): JsonResponse
    {
        try {
            $limit = min((int) $request->get('limit', 10), 50);
            $categoryId = $request->get('category_id');
            $page = max((int) $request->get('page', 1), 1);

            $payload = Cache::remember(
                'mobile.events.featured.'.md5(json_encode([$categoryId, $limit, $page])),
                self::CATALOG_CACHE_SECONDS,
                function () use ($limit, $categoryId) {
                    $query = $this->catalogQuery();

                    if ($categoryId) {
                        $query->where('main_category_id', $categoryId);
                    }

                    $query->orderBy('id', 'desc');
                    $events = $query->paginate($limit);

                    return [
                        'events' => $this->formatCatalogPage($events),
                        'pagination' => $this->formatPagination($events),
                    ];
                }
            );

            return $this->sendResponse(
                ['events' => $this->applyViewerState($payload['events'], Auth::id())],
                'Eventos em destaque recuperados com sucesso',
                $payload['pagination']
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
            $limit = min((int) $request->get('limit', 20), 100);
            $page = max((int) $request->get('page', 1), 1);
            $userCity = ($lat && $lng) ? Auth::user()?->city_id : null;

            $payload = Cache::remember(
                'mobile.events.upcoming.'.md5(json_encode([$userCity, $radius, $limit, $page])),
                self::CATALOG_CACHE_SECONDS,
                function () use ($limit, $userCity) {
                    $query = $this->catalogQuery()
                        ->where('start_date', '>=', now()->format('Y-m-d'));

                    if ($userCity) {
                        $query->where('city_id', $userCity);
                    }

                    $events = $query->orderBy('start_date', 'desc')->paginate($limit);

                    return [
                        'events' => $this->formatCatalogPage($events),
                        'pagination' => $this->formatPagination($events),
                    ];
                }
            );

            return $this->sendResponse(
                ['events' => $this->applyViewerState($payload['events'], Auth::id())],
                'Próximos eventos recuperados com sucesso',
                $payload['pagination']
            );

        } catch (\Exception $e) {
            return $this->sendError('Erro ao buscar próximos eventos', [], 500);
        }
    }

    /**
     * Get events with map coordinates.
     */
    public function map(Request $request): JsonResponse
    {
        try {
            $limit = min((int) $request->get('limit', 200), 300);
            $categoryId = $request->get('category_id');
            $city = $request->get('city');
            $cityId = $request->get('city_id');

            $events = Cache::remember(
                'mobile.events.map.'.md5(json_encode([$limit, $categoryId, $city, $cityId])),
                self::CATALOG_CACHE_SECONDS,
                function () use ($limit, $categoryId, $city, $cityId) {
                    $query = $this->catalogQuery()
                        ->whereNotNull('latitude')
                        ->whereNotNull('longitude')
                        ->where('latitude', '!=', 0)
                        ->where('longitude', '!=', 0);

                    if ($categoryId) {
                        $query->where('main_category_id', $categoryId);
                    }

                    if ($city) {
                        $query->whereHas('city', function (Builder $q) use ($city) {
                            $q->where('name', 'like', "%{$city}%");
                        });
                    }

                    if ($cityId) {
                        $query->where('city_id', $cityId);
                    }

                    return $this->formatCatalogPage(
                        $query->orderBy('start_date', 'desc')->limit($limit)->get()
                    );
                }
            );

            return $this->sendResponse(
                ['events' => $this->applyViewerState($events, Auth::id())],
                'Eventos do mapa recuperados com sucesso'
            );
        } catch (\Exception $e) {
            return $this->sendError('Erro ao buscar eventos do mapa', [], 500);
        }
    }

    /**
     * Search events with filters.
     */
    public function search(Request $request): JsonResponse
    {
        try {
            $query = $this->catalogQuery();

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

            if ($organizerId = $request->get('organizer_id')) {
                $query->where('user_id', $organizerId);
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
            $sortOrder = $request->get('sort_order', 'desc');

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
                    $query->orderBy('start_date', 'desc');
            }

            $perPage = min($request->get('per_page', 20), 100);
            $events = $query->paginate($perPage);
            $events->setCollection($this->presentEvents($events, Auth::id()));

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
                            //    ->whereIn('status_id', [1,2,3])
                               ->where('status_id', 2)
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
            $event = Event::withMobileCatalog()
                ->with([
                    'shopProducts' => function ($query) {
                        $query->active()->with('variants');
                    },
                ])
                ->find($id);

            if (!$event) {
                return $this->sendError('Evento não encontrado', [], 404);
            }

            $formattedEvent = $this->presentEvents(collect([$event]), Auth::id())->first();

            return $this->sendResponse(
                ['event' => $formattedEvent],
                'Detalhes do evento recuperados com sucesso'
            );

        } catch (\Exception $e) {
            return $this->sendError('Erro ao buscar detalhes do evento', [], 500);
        }
    }

    public function toggleEvent($id): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if (!$user) {
                return $this->sendError('Usuário não autenticado', [], 401);
            }

            $event = Event::find($id);
            
            if (!$event) {
                return $this->sendError('Evento não encontrado', [], 404);
            }

            // Verificar se já é favorito
            $favorite = FavoriteEvent::where('user_id', $user->id)
                                   ->where('event_id', $event->id)
                                   ->first();

            $isFavorited = false;
            $message = '';

            if ($favorite) {
                // Se já é favorito, remover
                $favorite->delete();
                $isFavorited = false;
                $message = 'Evento removido dos favoritos';
            } else {
                // Se não é favorito, adicionar
                FavoriteEvent::create([
                    'user_id' => $user->id,
                    'event_id' => $event->id
                ]);
                $isFavorited = true;
                $message = 'Evento adicionado aos favoritos';
            }

            return $this->sendResponse(
                [
                    'event_id' => $event->id,
                    'is_favorited' => $isFavorited,
                    'favorites_count' => FavoriteEvent::where('event_id', $event->id)->count()
                ],
                $message
            );

        } catch (\Exception $e) {
            Log::error('Erro ao alterar favorito: ' . $e->getMessage());
            return $this->sendError('Erro ao alterar favorito', ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Get all favorite events for authenticated user.
     */
    public function favorites(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if (!$user) {
                return $this->sendError('Usuário não autenticado', [], 401);
            }

            $perPage = min($request->get('per_page', 20), 100);
            $sortBy = $request->get('sort_by', 'created_at'); // created_at, date, name
            $sortOrder = $request->get('sort_order', 'desc');

            // Buscar eventos favoritos com relacionamentos usando leftJoin para evitar ambiguidade
            $query = Event::query()
            ->leftJoin('favorite_events', 'events.id', '=', 'favorite_events.event_id')
            ->where('favorite_events.user_id', $user->id)
            ->select('events.*', 'favorite_events.created_at as favorited_at')
            ->withMobileCatalog();

            // Aplicar ordenação
            switch ($sortBy) {
                case 'date':
                    $query->orderBy('events.start_date', $sortOrder);
                    break;
                case 'name':
                    $query->orderBy('events.name', $sortOrder);
                    break;
                case 'created_at':
                default:
                    // Ordenar pela data que foi favoritado
                    $query->orderBy('favorite_events.created_at', $sortOrder);
                    break;
            }

            $events = $query->paginate($perPage);

            // Verificar se há eventos favoritos
            if ($events->total() == 0) {
                return $this->sendResponse(
                    [
                        'events' => [],
                        'total_favorites' => 0
                    ],
                    'Nenhum evento favorito encontrado',
                    [
                        'pagination' => [
                            'current_page' => 1,
                            'per_page' => $perPage,
                            'total' => 0,
                            'total_pages' => 0,
                            'has_more_pages' => false
                        ]
                    ]
                );
            }

            $events->setCollection($this->presentEvents($events, $user->id));

            // Contar total de favoritos do usuário
            $totalFavorites = FavoriteEvent::where('user_id', $user->id)->count();

            return $this->sendResponse(
                [
                    'events' => $events->items(),
                    'total_favorites' => $totalFavorites
                ],
                'Eventos favoritos recuperados com sucesso',
                $this->formatPagination($events)
            );

        } catch (\Exception $e) {
            return $this->sendError('Erro ao buscar eventos favoritos', ['error' => $e->getMessage()], 500);
        }
    }

    private function catalogQuery(): Builder
    {
        return Event::query()->withMobileCatalog()->where('status_id', 2);
    }
}