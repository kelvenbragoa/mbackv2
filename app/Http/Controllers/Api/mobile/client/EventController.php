<?php

namespace App\Http\Controllers\Api\mobile\client;

use App\Models\Event;
use App\Models\FavoriteEvent;
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
            ])->whereIn('status_id', [1,2,3]); // Assumindo que status 1 é ativo

            if ($categoryId) {
                $query->where('main_category_id', $categoryId);
            }

            // Ordernar por data mais próxima
            // $query->where('start_date', '>=', now()->format('Y-m-d'))
            //       ->orderBy('start_date', 'asc');
            $query->orderBy('id', 'desc');

            $events = $query->paginate($limit);
            

            $formattedEvents = $events->getCollection()->map(function ($event) {
                return $this->formatEvent($event, Auth::id());
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
            ])->whereIn('status_id', [1,2,3])
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
            ])->whereIn('status_id', [1,2,3]);

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
                               ->whereIn('status_id', [1,2,3])
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
            $query = Event::with([
                'category', 'city', 'province', 'tickets', 'sells', 'review', 'like'
            ])
            ->leftJoin('favorite_events', 'events.id', '=', 'favorite_events.event_id')
            ->where('favorite_events.user_id', $user->id)
            ->select('events.*', 'favorite_events.created_at as favorited_at'); // Evitar ambiguidade na coluna id

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

            $formattedEvents = $events->getCollection()->map(function ($event) use ($user) {
                return $this->formatEvent($event, $user->id);
            });

            $events->setCollection($formattedEvents);

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

    
}