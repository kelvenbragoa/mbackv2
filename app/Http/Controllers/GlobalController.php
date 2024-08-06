<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Notifications\TicketPaid;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Twilio\Rest\Client;

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


}
