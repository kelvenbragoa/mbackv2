<?php

namespace App\Http\Controllers\Api\mobile\client;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
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
     * Format event data for API response.
     */
    protected function formatEvent($event, $userId = null): array
    {
        // Calcular preço mínimo e máximo dos tickets
        $priceRange = [
            'min' => 0,
            'max' => 0,
            'currency' => 'MT'
        ];

        if ($event->tickets->count() > 0) {
            $prices = $event->tickets->pluck('price')->map(function($price) {
                return (float) $price;
            });
            
            $priceRange['min'] = $prices->min();
            $priceRange['max'] = $prices->max();
        }

        // Verificar se é favorito do usuário
        $isFavorite = false;
        if ($userId) {
            $isFavorite = $event->like()->where('user_id', $userId)->exists();
        }

        // Verificar se está esgotado
        $isSoldOut = $event->tickets->sum('max_qtd') <= $event->sells->count();

        // Calcular avaliação média
        $rating = [
            'average' => $event->review->avg('rating') ?? 0,
            'total_reviews' => $event->review->count()
        ];

        return [
            'id' => $event->id,
            'title' => $event->name,
            'user' => $event->user->name,
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
                'latitude' => 0, // Adicionar campos se necessário
                'longitude' => 0
            ],
            'date_time' => $event->start_date . 'T' . $event->start_time . ':00Z',
            'end_date_time' => $event->end_date . 'T' . $event->end_time . ':00Z',
            'price_range' => $priceRange,
            'ticket_types' => $event->tickets->map(function($ticket) {
                $soldQuantity = $ticket->sells->count();
                return [
                    'id' => $ticket->id,
                    'name' => $ticket->name,
                    'description' => $ticket->description,
                    'price' => (float) $ticket->price,
                    'available_quantity' => max(0, $ticket->max_qtd - $soldQuantity),
                    'total_quantity' => $ticket->max_qtd
                ];
            })->toArray(),
            'is_favorite' => $isFavorite,
            'is_sold_out' => $isSoldOut,
            'rating' => $rating,
            'price'=>$event->price,
            // 'price'=> $event->tickets()->orderBy('price', 'asc')->first() ? $event->tickets()->orderBy('price', 'asc')->first()->price : 0,
            'tags' => [], // Implementar se necessário
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
            'events_count' => $category->events->count(),
            'is_active' => true
        ];
    }

    /**
     * Format ticket data for API response.
     */
    protected function formatTicket($sell, $sellDetails): array
    {
        $ticket = $sellDetails->ticket;
        $event = $sell->event;
        
        return [
            'id' => 'TKT-' . date('Y') . '-' . str_pad($sell->id, 6, '0', STR_PAD_LEFT),
            'qr_code' => url('/qr/' . $sell->id), // Implementar geração de QR code
            'barcode' => $sell->id . str_pad($ticket->id, 10, '0', STR_PAD_LEFT),
            'status' => $this->getTicketStatus($sell),
            'event' => [
                'id' => $event->id,
                'title' => $event->name,
                'image_url' => $event->image ? asset('storage/' . $event->image) : null,
                'date_time' => $event->start_date . 'T' . $event->start_time . ':00Z',
                'venue' => [
                    'name' => $event->location ?? $event->address,
                    'address' => $event->address
                ]
            ],
            'ticket_type' => [
                'id' => $ticket->id,
                'name' => $ticket->name,
                'section' => null,
                'row' => null,
                'seat' => null
            ],
            'purchase' => [
                'id' => 'PUR-' . date('Y') . '-' . str_pad($sell->id, 4, '0', STR_PAD_LEFT),
                'total_amount' => (float) $sell->total,
                'currency' => 'AOA',
                'payment_method' => 'credit_card', // Implementar se necessário
                'purchased_at' => $sell->created_at->toISOString()
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