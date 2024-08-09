<?php

namespace App\Http\Controllers;

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

class GlobalController extends Controller
{
    //
    public function sendtwilio()
    {
        $url = $this->ticketdownload(8);
        $receiverNumber = 'whatsapp:+258842648618'; // Replace with the recipient's phone number
        $message = 'mensagem de teste'; // Replace with your desired message
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

            return 'SMS Sent Successfully.';
        } catch (Exception $e) {
            return 'Error: ' . $e->getMessage();
        }
    }

    public function sendSms(Request $request)
    {
        // $receiverNumber = 'whatsapp:+258842648618'; // Replace with the recipient's phone number
        // $message = 'mensagem de teste'; // Replace with your desired message

        // $sid = env('TWILIO_SID');
        // $token = env('TWILIO_AUTH_TOKEN');
        // $fromNumber = env('TWILIO_NUMBER');

        // try {
        //     $client = new Client($sid, $token);
        //     $client->messages->create($receiverNumber, [
        //         'from' => $fromNumber,
        //         'body' => $message
        //     ]);

        //     return 'SMS Sent Successfully.';
        // } catch (Exception $e) {
        //     return 'Error: ' . $e->getMessage();
        // }
        
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
            return response()->json($e);
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
        
    
}




