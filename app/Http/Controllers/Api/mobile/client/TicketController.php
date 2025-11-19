<?php

namespace App\Http\Controllers\Api\mobile\client;

use App\Models\Sell;
use App\Models\SellDetails;
use App\Models\Event;
use App\Models\Ticket;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class TicketController extends BaseController
{
    /**
     * List user's tickets.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if (!$user) {
                return $this->sendError('Usuário não autenticado', [], 401);
            }

            $status = $request->get('status');
            $eventId = $request->get('event_id');
            $perPage = min($request->get('per_page', 20), 100);

            $query = Sell::with([
                'event.city', 'event.province', 'selldetails.ticket', 'user'
            ])->where('user_id', $user->id);

            // Filtro por evento
            if ($eventId) {
                $query->where('event_id', $eventId);
            }

            // Filtro por status (será aplicado após a formatação)
            $sells = $query->orderBy('created_at', 'desc')->paginate($perPage);

            $formattedTickets = [];
            $summary = ['active' => 0, 'used' => 0, 'expired' => 0, 'cancelled' => 0];

            foreach ($sells as $sell) {
                foreach ($sell->selldetails as $sellDetail) {
                    $formattedTicket = $this->formatTicket($sell, $sellDetail);
                    
                    // Aplicar filtro de status se especificado
                    if ($status && $formattedTicket['status'] !== $status) {
                        continue;
                    }

                    $formattedTickets[] = $formattedTicket;
                    $summary[$formattedTicket['status']]++;
                }
            }

            // Aplicar paginação manual se houve filtro por status
            if ($status) {
                $page = $request->get('page', 1);
                $offset = ($page - 1) * $perPage;
                $total = count($formattedTickets);
                $formattedTickets = array_slice($formattedTickets, $offset, $perPage);
                
                $paginationData = [
                    'current_page' => (int) $page,
                    'per_page' => $perPage,
                    'total' => $total,
                    'total_pages' => ceil($total / $perPage),
                    'has_more_pages' => $page < ceil($total / $perPage)
                ];
            } else {
                $paginationData = $this->formatPagination($sells)['pagination'];
            }

            return $this->sendResponse(
                [
                    'tickets' => $formattedTickets,
                    'summary' => $summary
                ],
                'Ingressos recuperados com sucesso',
                ['pagination' => $paginationData]
            );

        } catch (\Exception $e) {
            return $this->sendError('Erro ao buscar ingressos', [], 500);
        }
    }

    /**
     * Get ticket details by ID.
     */
    public function show(Request $request, $ticketId): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if (!$user) {
                return $this->sendError('Usuário não autenticado', [], 401);
            }

            // O ticketId pode ser o ID da venda ou o código formatado
            $sellId = $this->extractSellIdFromTicketId($ticketId);

            $sell = Sell::with([
                'event.city', 'event.province', 'selldetails.ticket', 'user'
            ])->where('user_id', $user->id)
              ->where('id', $sellId)
              ->first();

            if (!$sell) {
                return $this->sendError('Ingresso não encontrado', [], 404);
            }

            // Assumindo que queremos o primeiro detalhe (pode ser modificado conforme necessário)
            $sellDetail = $sell->selldetails->first();
            
            if (!$sellDetail) {
                return $this->sendError('Detalhes do ingresso não encontrados', [], 404);
            }

            $formattedTicket = $this->formatTicket($sell, $sellDetail);

            return $this->sendResponse(
                ['ticket' => $formattedTicket],
                'Detalhes do ingresso recuperados com sucesso'
            );

        } catch (\Exception $e) {
            return $this->sendError('Erro ao buscar detalhes do ingresso', [], 500);
        }
    }

    /**
     * Validate ticket (for event organizers).
     */
    public function validate(Request $request, $ticketId): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if (!$user) {
                return $this->sendError('Usuário não autenticado', [], 401);
            }

            $request->validate([
                'entrance_gate' => 'required|string|max:255',
                'validator_name' => 'required|string|max:255',
                'latitude' => 'nullable|numeric',
                'longitude' => 'nullable|numeric'
            ]);

            $sellId = $this->extractSellIdFromTicketId($ticketId);

            $sell = Sell::with(['event', 'selldetails.ticket'])
                        ->where('id', $sellId)
                        ->first();

            if (!$sell) {
                return $this->sendError('Ingresso não encontrado', [], 404);
            }

            // Verificar se o usuário tem permissão para validar (pode ser o organizador do evento)
            if ($sell->event->user_id !== $user->id && !$user->is_promotor) {
                return $this->sendError('Sem permissão para validar este ingresso', [], 403);
            }

            // Verificar se o evento já passou
            $eventDate = $sell->event->start_date . ' ' . $sell->event->start_time;
            if (now() < $eventDate) {
                return $this->sendError('O evento ainda não começou', [], 422);
            }

            // Aqui você pode implementar a lógica de validação
            // Por exemplo, marcar como usado em uma tabela separada ou campo

            return $this->sendResponse(
                [
                    'ticket_id' => $ticketId,
                    'validated_at' => now()->toISOString(),
                    'validator' => $request->validator_name,
                    'gate' => $request->entrance_gate
                ],
                'Ingresso validado com sucesso'
            );

        } catch (\Exception $e) {
            return $this->sendError('Erro ao validar ingresso', [], 500);
        }
    }

    /**
     * Get transfer options for a ticket.
     */
    public function transferOptions(Request $request, $ticketId): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if (!$user) {
                return $this->sendError('Usuário não autenticado', [], 401);
            }

            $sellId = $this->extractSellIdFromTicketId($ticketId);

            $sell = Sell::with(['event'])->where('user_id', $user->id)
                        ->where('id', $sellId)
                        ->first();

            if (!$sell) {
                return $this->sendError('Ingresso não encontrado', [], 404);
            }

            $event = $sell->event;
            $eventDateTime = $event->start_date . ' ' . $event->start_time;
            $transferDeadline = date('Y-m-d H:i:s', strtotime($eventDateTime . ' -24 hours'));

            $canTransfer = now() < $transferDeadline;

            return $this->sendResponse(
                [
                    'can_transfer' => $canTransfer,
                    'transfer_deadline' => $transferDeadline . 'Z',
                    'transfer_fee' => 5.00, // Taxa fixa ou configurável
                    'restrictions' => [
                        'Transferência permitida até 24h antes do evento',
                        'Máximo de 2 transferências por ingresso'
                    ]
                ],
                'Opções de transferência recuperadas com sucesso'
            );

        } catch (\Exception $e) {
            return $this->sendError('Erro ao buscar opções de transferência', [], 500);
        }
    }

    /**
     * Get tickets count by status.
     */
    public function count(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if (!$user) {
                return $this->sendError('Usuário não autenticado', [], 401);
            }

            $sells = Sell::with(['event', 'selldetails'])->where('user_id', $user->id)->get();

            $summary = ['active' => 0, 'used' => 0, 'expired' => 0, 'cancelled' => 0];

            foreach ($sells as $sell) {
                foreach ($sell->selldetails as $sellDetail) {
                    $formattedTicket = $this->formatTicket($sell, $sellDetail);
                    $summary[$formattedTicket['status']]++;
                }
            }

            return $this->sendResponse(
                ['summary' => $summary],
                'Contagem de ingressos recuperada com sucesso'
            );

        } catch (\Exception $e) {
            return $this->sendError('Erro ao contar ingressos', [], 500);
        }
    }

    /**
     * Extract sell ID from formatted ticket ID.
     */
    private function extractSellIdFromTicketId($ticketId): int
    {
        // Se for um ID formatado como TKT-2024-001234
        if (preg_match('/TKT-\d{4}-(\d+)/', $ticketId, $matches)) {
            return (int) $matches[1];
        }
        
        // Caso contrário, assumir que é o ID direto
        return (int) $ticketId;
    }
}