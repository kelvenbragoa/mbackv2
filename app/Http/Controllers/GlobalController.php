<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Api\mobile\client\BaseController;
use App\Mail\SendTickets;
use App\Models\Barman;
use App\Models\Event;
use App\Models\Sell;
use App\Models\SellDetails;
use App\Models\User;
use App\Notifications\TicketPaid;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Twilio\Rest\Client;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\HttpFoundation\JsonResponse;

class GlobalController extends BaseController
{
    //
    public function sendtwilio()
    {
        // $url = $this->ticketdownload($sell_id);
        $url = "http://backend.mticket.co.mz/storage/tickets/ticket-8.pdf";
        // $receiverNumber = 'whatsapp:+258'.$number; // Replace with the recipient's phone number
        $receiverNumber = 'whatsapp:+258842648618'; // Replace with the recipient's phone number

        $message = 'Obrigado pela sua compra. O bilhete encontra-se em anexo. Este bilhete é intransmissivel. Para suporte contacte o seguinte email: suporte@mticket.co.mz, telefone: +258 84 264 8618 / 84 228 0974'; // Replace with your desired message
        // $mediaUrl = 'https://mticket.co.mz/demo/images/logo2.png'; // Replace with the media URL
        // $mediaUrl = 'https://inogest-atas.s3.amazonaws.com/meeting-attachment/qPBGAXU72RPO9m5M2VuS4jFDjO1yHhIiuvYUtQfP.pdf?response-content-disposition=attachment&X-Amz-Content-Sha256=UNSIGNED-PAYLOAD&X-Amz-Algorithm=AWS4-HMAC-SHA256&X-Amz-Credential=AKIAV2FXJ6Y2RJHFRKWL%2F20240809%2Fus-east-1%2Fs3%2Faws4_request&X-Amz-Date=20240809T054758Z&X-Amz-SignedHeaders=host&X-Amz-Expires=600&X-Amz-Signature=0e8696f1c8dd8c6c8595848645ebf6ce79476b389d5ab514fa1ffd1357c81de0';
        // $mediaUrl = 'https://backend.mticket.co.mz/ticketdownload/8';
        $mediaUrl = $url;
        $sid = env('TWILIO_SID');
        $token = env('TWILIO_AUTH_TOKEN');
        $fromNumber = env('TWILIO_NUMBER');

        try {
            $client = new Client($sid, $token);
            $client->messages->create($receiverNumber, [
                'from' => $fromNumber,
                'body' => $message,
                'mediaUrl'=>$mediaUrl
            ]);

            
        } catch (Exception $e) {
            
        }
    }

    public function sendSms(Request $request)
    {
        
        $email = "kelvenbragoa@hotmail.com";
        $name = "Kelven Bragoa";
        $mobile = "842648618";
        $id = 7;

        $sell = Sell::find($id);


        $detail = SellDetails::where('sell_id',$id)->get();

        $event = Event::find($sell->event_id);

        $msg_content = "Olá, {$name}. A sua compra para o evento {$event->name} foi realizada com sucesso. Segue o seu bilhete em anexo.";
        

        try {
            $number = '+258842648618';
            $ticket = new TicketPaid($detail,$event->id,$id,$msg_content,$number);
            Notification::send($number,$ticket);
            // $ticket->toWhatsapp();
            // dd($ticket->toWhatsapp());
        } catch (Exception $e) {
            return $e;
        }

        
    }



    public function sendmail(){

        // $ticket = 'ticket';
        // $msg = 'message';
        // $sell = 25;
        $email = "kelvenbragoa@hotmail.com";
        $name = "Kelven Bragoa";
        $mobile = "842648618";

        $id = 7;

        $sell = Sell::find($id);


        $detail = SellDetails::where('sell_id',$id)->get();

        $event = Event::find($sell->event_id);

        $msg_content = "Olá, {$name}. A sua compra para o evento {$event->name} foi realizada com sucesso. Segue o seu bilhete em anexo.";


        // $pdf = Pdf::loadView('pdf.ticket', compact('detail','event'))->setOptions([
        //     'defaultFont' => 'sans-serif',
        //     'isRemoteEnabled' => 'true'
        // ]);
        // return $pdf->setPaper('a4')->download('ticket.pdf');


        // Mail::to('kelvenbragoa@hotmail.com')->queue(new \App\Mail\SendTickets($name,$email,$mobile,$sell,$msg_content));
        // try {
        //     Mail::to('kelvenbragoa@hotmail.com')->send(new SendTickets($name,$email,$mobile,$sell,$msg_content));
        //         } catch (\Throwable $th) {
        //             return response()->json($th->getMessage());
        //         }
        // }
        // return new SendTickets($detail,$event->id,$id,$msg_content);
        try {
            Mail::to('kelvenbragoa@hotmail.com')->send(new SendTickets($detail,$event->id,$id,$msg_content));
                } catch (\Throwable $th) {
                    return response()->json($th->getMessage());
                }
        }


        public function ticketdownload($id){

            $sell = Sell::find($id);
            $detail = SellDetails::where('sell_id',$id)->get();
            $event = Event::find($sell->event_id);

            $pdf = PDF::loadView('pdf.ticket', compact('detail','event'));
            $fileName = 'ticket-'.$id.'.pdf';
            $pdf->save(storage_path('app/public/tickets/'.$fileName));

            return 'https://backend.mticket.co.mz/storage/tickets/ticket-'.$id.'.pdf';

        }


            /**
     * Generate slugs for all events that don't have one.
     */
    public function generateSlugs(): JsonResponse
    {
        try {
            // Buscar eventos sem slug ou com slug vazio/null
            $eventsWithoutSlug = Event::where(function ($query) {
                $query->whereNull('slug')
                      ->orWhere('slug', '')
                      ->orWhere('slug', 'like', '%-%-%'); // Slugs mal formados com muitos hífens
            })->get();

            if ($eventsWithoutSlug->isEmpty()) {
                return $this->sendResponse(
                    [
                        'updated_count' => 0,
                        'message' => 'Todos os eventos já possuem slug'
                    ],
                    'Nenhum evento para atualizar'
                );
            }

            $updatedCount = 0;
            $errors = [];

            foreach ($eventsWithoutSlug as $event) {
                try {
                    // Gerar slug baseado no nome do evento
                    $baseSlug = \Illuminate\Support\Str::slug($event->name);
                    
                    // Verificar se o slug já existe
                    $counter = 1;
                    $slug = $baseSlug;
                    
                    while (Event::where('slug', $slug)->where('id', '!=', $event->id)->exists()) {
                        $slug = $baseSlug . '-' . $counter;
                        $counter++;
                    }

                    // Atualizar o evento com o novo slug
                    $event->slug = $slug;
                    $event->save();
                    
                    $updatedCount++;

                } catch (\Exception $e) {
                    $errors[] = [
                        'event_id' => $event->id,
                        'event_name' => $event->name,
                        'error' => $e->getMessage()
                    ];
                }
            }

            return $this->sendResponse(
                [
                    'updated_count' => $updatedCount,
                    'total_found' => $eventsWithoutSlug->count(),
                    'errors' => $errors,
                    'success_rate' => $eventsWithoutSlug->count() > 0 ? 
                        round(($updatedCount / $eventsWithoutSlug->count()) * 100, 2) : 100
                ],
                "Slugs gerados com sucesso. {$updatedCount} eventos atualizados."
            );

        } catch (\Exception $e) {
            return $this->sendError('Erro ao gerar slugs', ['error' => $e->getMessage()], 500);
        }
    }
        
    
}




