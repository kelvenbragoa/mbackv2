<?php

namespace App\Http\Controllers\Api\mobile\client;

use App\Http\Controllers\Controller;
use App\Models\FavoriteEvent;
use App\Models\User;
use App\Support\LiveTicketAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class BaseController extends Controller
{
    /**
     * Success response method.
     */
    public function sendResponse($result, $message, $meta = null): JsonResponse
    {
        $response = [
            'success' => true,
            'message' => $message,
            'data' => $result
        ];

        if ($meta) {
            $response['meta'] = $meta;
        }

        return response()->json($response, 200);
    }

    /**
     * Return error response.
     */
    public function sendError($error, $errorMessages = [], $code = 404): JsonResponse
    {
        $response = [
            'success' => false,
            'message' => $error,
            'timestamp' => now()->toISOString()
        ];

        if (!empty($errorMessages)) {
            $response['errors'] = $errorMessages;
        }

        return response()->json($response, $code);
    }

    /**
     * Return validation error response.
     */
    public function sendValidationError($errorMessages, $message = 'Dados inválidos'): JsonResponse
    {
        return $this->sendError($message, $errorMessages, 422);
    }

    /**
     * Format pagination meta data.
     */
    protected function formatPagination($paginator): array
    {
        return [
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'total_pages' => $paginator->lastPage(),
                'has_more_pages' => $paginator->hasMorePages()
            ]
        ];
    }

    /**
     * @return array{active: bool, ends_at: null, has_expiry: bool}
     */
    protected function inactiveLiveAccess(): array
    {
        return [
            'active' => false,
            'ends_at' => null,
            'has_expiry' => false,
        ];
    }

    /**
     * Format a page of events with one favorites query and one live-access query.
     */
    protected function presentEvents($events, $userId = null): Collection
    {
        $collection = $events instanceof \Illuminate\Contracts\Pagination\Paginator
            ? $events->getCollection()
            : collect($events);

        $ids = $collection->pluck('id')->map(fn ($id) => (int) $id)->all();
        $favoriteIds = $this->favoriteIdLookup($userId, $ids);
        $liveAccess = $userId ? LiveTicketAccess::payloadsFor((int) $userId, $ids) : [];
        $inactive = $this->inactiveLiveAccess();

        return $collection->map(function ($event) use ($favoriteIds, $liveAccess, $inactive) {
            $id = (int) $event->id;

            return $this->formatEvent($event, null, [
                'is_favorite' => isset($favoriteIds[$id]),
                'live_access' => $liveAccess[$id] ?? $inactive,
            ]);
        })->values();
    }

    /**
     * Public catalog rows. Viewer fields are filled in afterwards so the page can be cached.
     */
    protected function formatCatalogPage($events): array
    {
        $inactive = $this->inactiveLiveAccess();
        $collection = $events instanceof \Illuminate\Contracts\Pagination\Paginator
            ? $events->getCollection()
            : collect($events);

        return $collection->map(function ($event) use ($inactive) {
            return $this->formatEvent($event, null, [
                'is_favorite' => false,
                'live_access' => $inactive,
            ]);
        })->values()->all();
    }

    protected function applyViewerState(array $events, $userId): array
    {
        if (! $userId || $events === []) {
            return $events;
        }

        $ids = array_map(fn ($event) => (int) ($event['id'] ?? 0), $events);
        $favoriteIds = $this->favoriteIdLookup($userId, $ids);
        $liveAccess = LiveTicketAccess::payloadsFor((int) $userId, $ids);
        $inactive = $this->inactiveLiveAccess();

        foreach ($events as &$event) {
            $id = (int) ($event['id'] ?? 0);
            $event['is_favorite'] = isset($favoriteIds[$id]);
            $event['live_access'] = $liveAccess[$id] ?? $inactive;
        }

        return $events;
    }

    /**
     * @param  array<int, int>  $eventIds
     * @return array<int, true>
     */
    protected function favoriteIdLookup($userId, array $eventIds): array
    {
        if (! $userId || $eventIds === []) {
            return [];
        }

        return FavoriteEvent::query()
            ->where('user_id', $userId)
            ->whereIn('event_id', $eventIds)
            ->pluck('event_id')
            ->mapWithKeys(fn ($id) => [(int) $id => true])
            ->all();
    }

    /**
     * Format event data for API response.
     */
    protected function formatEvent($event, $userId = null, array $overrides = []): array
    {
        $tickets = $event->tickets;

        // Calcular preço mínimo e máximo dos tickets
        $priceRange = [
            'min' => 0,
            'max' => 0,
            'currency' => 'MT'
        ];

        if ($tickets->count() > 0) {
            $prices = $tickets->pluck('price')->map(function($price) {
                return (float) $price;
            });
            
            $priceRange['min'] = $prices->min();
            $priceRange['max'] = $prices->max();
        }

        if (array_key_exists('is_favorite', $overrides)) {
            $isFavorite = (bool) $overrides['is_favorite'];
        } else {
            $isFavorite = false;
            if ($userId) {
                $isFavorite = FavoriteEvent::where('user_id', $userId)
                    ->where('event_id', $event->id)
                    ->exists();
            }
        }

        // Same rule as before: capacity against number of orders, counted in SQL when available.
        $soldOrders = array_key_exists('sells_count', $event->getAttributes())
            ? (int) $event->getAttributes()['sells_count']
            : $event->sells->count();
        $isSoldOut = $tickets->sum('max_qtd') <= $soldOrders;

        if (array_key_exists('review_count', $event->getAttributes())) {
            $rating = [
                'average' => (float) ($event->review_avg_rating ?? 0),
                'total_reviews' => (int) $event->review_count,
            ];
        } else {
            $rating = [
                'average' => $event->review->avg('rating') ?? 0,
                'total_reviews' => $event->review->count()
            ];
        }

        $cheapest = $tickets->sortBy(fn ($ticket) => (string) $ticket->price)->first();
        $price = $cheapest ? (int) $cheapest->price : 0;

        $organizer = $event->user;
        $organizerName = $organizer?->company_name ?: $organizer?->name;
        $organizerImage = $organizer?->image
            ? asset('storage/' . ltrim($organizer->image, '/'))
            : null;

        return [
            'id' => $event->id,
            'slug' => $event->slug,
            'title' => $event->name,
            'user' => $organizerName,
            'organizer_id' => $organizer?->id ?? $event->user_id,
            'organizer_slug' => $organizer?->slug,
            'organizer_image_url' => $organizerImage,
            'description' => $event->description,
            'short_description' => Str::limit($event->description, 100),
            'image_url' => $event->image ? asset('storage/' . $event->image) : null,
            'banner_url' => $event->image ? asset('storage/' . $event->image) : null,
            'category' => [
                'id' => $event->category->id ?? null,
                'name' => $event->category->name ?? null,
                'icon' => 'music', // Default icon
                'color' => '#FF6B6B' // Default color
            ],
            'venue' => [
                'id' => 1,
                'name' => $event->location ?? $event->address,
                'address' => $event->address,
                'city' => $event->city->name ?? null,
                'state' => $event->province->name ?? null,
                'latitude' => $event->latitude !== null ? (float) $event->latitude : null,
                'longitude' => $event->longitude !== null ? (float) $event->longitude : null,
            ],
            'date_time' => $event->start_date . 'T' . $event->start_time . ':00Z',
            'end_date_time' => $event->end_date . 'T' . $event->end_time . ':00Z',
            'price_range' => $priceRange,
            'is_sales_closed' => $event->isSalesClosed(),
            'type_event_id' => $event->type_event_id,
            'ticket_types' => $tickets->map(function($ticket) use ($event) {
                return $this->formatTicketType($ticket, $event);
            })->toArray(),
            'shop_products' => $event->relationLoaded('shopProducts')
                ? $event->shopProducts->map->toPublicArray()->values()->all()
                : [],
            'is_favorite' => $isFavorite,
            'is_sold_out' => $isSoldOut,
            'rating' => $rating,
            'price'=>$price,
            'tags' => [], // Implementar se necessário
            'live_access' => $overrides['live_access'] ?? LiveTicketAccess::accessPayload(
                $event,
                $userId ? User::find($userId) : null
            ),
            'created_at' => $event->created_at->toISOString(),
            'updated_at' => $event->updated_at->toISOString()
        ];
    }

    /**
     * Format category data for API response.
     */
    protected function formatCategory($category): array
    {
        return [
            'id' => $category->id,
            'name' => $category->name,
            'icon' => 'music', // Default icon
            'color' => '#FF6B6B', // Default color  
            'image_url' => $category->image ? asset('storage/' . $category->image) : null,
            'events_count' => array_key_exists('events_count', $category->getAttributes())
                ? (int) $category->events_count
                : $category->events->count(),
            'is_active' => true
        ];
    }

    /**
     * Format a ticket type for the mobile catalog / selection screen.
     * Order: event date first, then ticket sale window, then stock.
     */
    protected function formatTicketType($ticket, $event = null): array
    {
        $event ??= $ticket->relationLoaded('event') ? $ticket->event : null;
        $availableQuantity = $ticket->availableQuantity();
        $saleStatus = $ticket->saleStatus($availableQuantity, $event);
        $exposeRemaining = $ticket->shouldExposeRemainingQuantity($availableQuantity, $event);

        $formFields = $ticket->relationLoaded('formFields')
            ? $ticket->formFields
            : $ticket->formFields()->orderBy('sort_order')->orderBy('id')->get();

        return [
            'id' => $ticket->id,
            'name' => $ticket->name,
            'description' => $ticket->description,
            'price' => (float) $ticket->price,
            'price_aoa' => $ticket->price_aoa !== null && (float) $ticket->price_aoa > 0
                ? (float) $ticket->price_aoa
                : null,
            // Hide remaining stock when blocked by event/ticket dates.
            'available_quantity' => $exposeRemaining ? $availableQuantity : null,
            'total_quantity' => $exposeRemaining ? (int) $ticket->max_qtd : null,
            'max_per_order' => (int) $ticket->is_live === 1
                ? 1
                : ($ticket->max_per_order !== null ? (int) $ticket->max_per_order : null),
            'start_date' => $ticket->start_date,
            'end_date' => $ticket->end_date,
            'start_time' => $ticket->start_time,
            'end_time' => $ticket->end_time,
            'is_on_sale' => $saleStatus === 'available',
            'sale_status' => $saleStatus,
            'is_live' => (bool) $ticket->is_live,
            'valid_for_days' => $ticket->valid_for_days !== null ? (int) $ticket->valid_for_days : null,
            'form_fields' => $formFields->map(function ($field) {
                return [
                    'id' => $field->id,
                    'field_key' => $field->field_key,
                    'label' => $field->label,
                    'type' => $field->type,
                    'required' => (bool) $field->required,
                    'options' => $field->options ?? [],
                    'terms_text' => $field->terms_text,
                    'sort_order' => (int) ($field->sort_order ?? 0),
                ];
            })->values()->all(),
        ];
    }

    /**
     * Format ticket data for API response.
     */
    protected function formatTicket($sell, $sellDetails): array
    {
        $ticket = $sellDetails->ticket;
        $event = $sell->event;
        $isLive = $ticket && (int) $ticket->is_live === 1;
        $qrcodemticket = $isLive
            ? null
            : ($sellDetails->qrcode ?: '{"s":'.$sellDetails->status.',"i":'.$sellDetails->id.',"ie":'.$event->id.'}');
        
        return [
            'id' => 'TKT-' . date('Y') . '-' . str_pad($sell->id, 6, '0', STR_PAD_LEFT),
            'id_mticket'=>$sellDetails->id,
            'ticket_number' => $isLive ? null : $sellDetails->ticket_number,
            'qrcode_mticket'=>$qrcodemticket,
            'qr_code' => $isLive ? null : url('/qr/' . $sell->id),
            'barcode' => $sell->id . str_pad($ticket->id, 10, '0', STR_PAD_LEFT),
            'status' => $this->getTicketStatus($sell),
            'status_id'=>$sellDetails->status,
            'event' => [
                'id' => $event->id,
                'slug' => $event->slug,
                'title' => $event->name,
                'image_url' => $event->image ? asset('storage/' . $event->image) : null,
                'date_time' => $event->start_date . 'T' . $event->start_time . ':00Z',
                'venue' => [
                    'name' => $event->location ?? $event->address,
                    'address' => $event->address,
                    'latitude' => $event->latitude !== null ? (float) $event->latitude : null,
                    'longitude' => $event->longitude !== null ? (float) $event->longitude : null,
                ]
            ],
            'ticket_type' => [
                'id' => $ticket->id,
                'name' => $ticket->name,
                'section' => null,
                'row' => null,
                'seat' => null,
                'is_live' => $isLive,
                'valid_for_days' => $ticket && $ticket->valid_for_days !== null
                    ? (int) $ticket->valid_for_days
                    : null,
            ],
            'access_ends_at' => $isLive && $sellDetails->access_ends_at
                ? $sellDetails->access_ends_at->toIso8601String()
                : null,
            'purchase' => [
                'id' => 'PUR-' . date('Y') . '-' . str_pad($sell->id, 4, '0', STR_PAD_LEFT),
                'total_amount' => (float) $sell->total,
                'currency' => 'MT',
                'payment_method' => 'credit_card', // Implementar se necessário
                'purchased_at' => $sell->created_at->toIso8601String()
            ],
            'holder' => [
                'name' => $sell->user->name,
                'email' => $sell->user->email,
                'document' => $sell->user->bi ?? null
            ],
            'validation' => [
                'validated_at' => null, // Implementar se necessário
                'validator_name' => null,
                'entrance_gate' => null
            ],
            'created_at' => $sell->created_at->toISOString(),
            'expires_at' => $event->end_date . 'T23:59:59Z'
        ];
    }

    /**
     * Get ticket status based on sell and event data.
     */
    private function getTicketStatus($sell): string
    {
        $event = $sell->event;
        $eventEndDate = $event->end_date . ' ' . $event->end_time;
        
        if (now() > $eventEndDate) {
            return 'expired';
        }
        
        // Implementar lógica de validação se necessário
        return 'active';
    }
}