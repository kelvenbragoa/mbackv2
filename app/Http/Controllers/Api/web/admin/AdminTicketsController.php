<?php

namespace App\Http\Controllers\Api\web\admin;

use App\Http\Controllers\Controller;
use App\Support\TicketFile;
use App\Mail\SendTickets;
use App\Models\SellDetails;
use App\Notifications\TicketPaid;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;

class AdminTicketsController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        if ($denied = $this->denyNonAdmin()) {
            return $denied;
        }

        $tickets = SellDetails::query()
            ->when($request->query('query'), function ($query, $searchQuery) {
                $query->where(function ($inner) use ($searchQuery) {
                    $inner->where('id', 'like', "%{$searchQuery}%")
                        ->orWhere('ticket_number', 'like', "%{$searchQuery}%")
                        ->orWhere('name', 'like', "%{$searchQuery}%")
                        ->orWhere('email', 'like', "%{$searchQuery}%")
                        ->orWhere('mobile', 'like', "%{$searchQuery}%")
                        ->orWhereHas('event', function ($event) use ($searchQuery) {
                            $event->where('name', 'like', "%{$searchQuery}%");
                        });
                });
            })
            ->when($request->query('status') !== null && $request->query('status') !== '', function ($query) use ($request) {
                $query->where('status', (int) $request->query('status'));
            })
            ->when($request->query('event_id'), function ($query, $eventId) {
                $query->where('event_id', $eventId);
            })
            ->with('event:id,name,start_date')
            ->with('sell.transaction')
            ->with('ticket:id,name,price')
            ->orderBy('id', 'desc')
            ->paginate($this->perPage($request))
            ->appends($request->query());

        return response()->json([
            'tickets' => $tickets,
            'summary' => $this->summary(),
        ]);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        if ($denied = $this->denyNonAdmin()) {
            return $denied;
        }

        $ticket = SellDetails::with(['event.province', 'event.city', 'sell.transaction', 'ticket'])
            ->find($id);

        if (!$ticket) {
            return response()->json(['message' => 'Bilhete não encontrado.'], 404);
        }

        return response()->json([
            'ticket' => $ticket,
        ]);
    }

    public function resend(Request $request, string $id)
    {
        if ($denied = $this->denyNonAdmin()) {
            return $denied;
        }

        $data = $request->validate([
            'email' => ['required', 'email'],
            'mobile' => ['nullable', 'string', 'max:30'],
            'pdf' => ['nullable', 'file', 'max:10240'],
        ]);

        $ticket = SellDetails::with(['event', 'ticket', 'sell'])->find($id);
        if (! $ticket) {
            return response()->json(['message' => 'Bilhete não encontrado.'], 404);
        }

        $isLive = $ticket->ticket && (int) $ticket->ticket->is_live === 1;
        $pdfBinary = null;

        if (! $isLive) {
            if (! $request->hasFile('pdf')) {
                return response()->json(['message' => 'O PDF do bilhete é obrigatório.'], 422);
            }

            $pdfBinary = file_get_contents($request->file('pdf')->getRealPath());
            if ($pdfBinary === false || $pdfBinary === '' || ! str_starts_with($pdfBinary, '%PDF')) {
                return response()->json(['message' => 'PDF inválido.'], 422);
            }
        }

        $email = $data['email'];
        $mobile = trim((string) ($data['mobile'] ?? ''));

        $ticket->email = $email;
        $ticket->mobile = $mobile !== '' ? $mobile : $ticket->mobile;
        $ticket->save();

        if ($ticket->sell_id) {
            SellDetails::where('sell_id', $ticket->sell_id)->update([
                'email' => $email,
                'mobile' => $ticket->mobile,
            ]);
            $ticket->sell?->update([
                'email' => $email,
                'mobile' => $ticket->mobile,
            ]);
        }

        $event = $ticket->event;
        if (! $event) {
            return response()->json(['message' => 'Evento não encontrado.'], 422);
        }

        $msg = "Olá, {$ticket->name}. Segue o seu bilhete para o evento {$event->name}.";
        $detail = SellDetails::where('id', $ticket->id)->get();
        $sellId = $ticket->sell_id ?: $ticket->id;
        $attachment = $isLive ? '' : $pdfBinary;

        try {
            Mail::to($email)->send(new SendTickets($detail, $event->id, $sellId, $msg, $attachment));

            if (! $isLive && $ticket->mobile && $pdfBinary) {
                $url = TicketFile::temporaryUrl((int) $sellId, $pdfBinary);
                if ($url) {
                    Notification::send($ticket->mobile, new TicketPaid($url, $sellId, $ticket->mobile));
                }
            }
        } catch (\Throwable $th) {
            Log::error($th->getMessage());

            return response()->json(['message' => 'Contactos gravados, mas não foi possível reenviar o bilhete.'], 500);
        }

        return response()->json([
            'ok' => true,
            'ticket' => $ticket->fresh(['event.province', 'event.city', 'sell.transaction', 'ticket']),
        ]);
    }

    private function summary(): array
    {
        $counts = SellDetails::select('status', DB::raw('COUNT(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        return [
            'total' => (int) $counts->sum(),
            'valid' => (int) ($counts[1] ?? 0),
            'used' => (int) ($counts[0] ?? 0),
        ];
    }

    private function denyNonAdmin()
    {
        if (Auth::user()->role_id != 1) {
            return response()->json(['message' => 'Sem permissão para aceder a esta área.'], 403);
        }

        return null;
    }

    private function perPage(Request $request): int
    {
        $perPage = (int) $request->query('per_page', 20);

        return in_array($perPage, [10, 20, 50], true) ? $perPage : 20;
    }
}
