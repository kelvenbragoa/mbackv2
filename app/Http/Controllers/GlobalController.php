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
    public function sendMessage()
    {
        $receiverNumber = '+258842648618'; // Replace with the recipient's phone number
        $message = 'mensagem de teste'; // Replace with your desired message

        $sid = env('TWILIO_SID');
        $token = env('TWILIO_AUTH_TOKEN');
        $fromNumber = env('TWILIO_NUMBER');

        try {
            $client = new Client($sid, $token);
            $client->messages->create($receiverNumber, [
                'from' => $fromNumber,
                'body' => $message
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

        try {
            $number = '+258842648618';
            $ticket = new TicketPaid($number);
            Notification::send($number,$ticket);
            // $ticket->toWhatsapp();
            // dd($ticket->toWhatsapp());
        } catch (Exception $e) {
            return response()->json($e->getMessage());
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
        
    
}




