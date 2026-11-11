<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\PayPay\PayPayService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class PayPayWebhookController extends Controller
{
    public function __invoke(Request $request, PayPayService $paypay)
    {
        // Parse the RAW body so TrimStrings / ConvertEmptyStringsToNull
        // cannot alter values that were signed.
        parse_str($request->getContent(), $data);

        try {
            if (! $paypay->verifyNotification($data)) {
                Log::warning('PayPay notification with invalid signature', ['ip' => $request->ip()]);

                return response('invalid signature', 400);
            }
            $paypay->handleNotification($data);
        } catch (Throwable $e) {
            // not "success": PayPay retries (2, 10, 10, 60, 120, 360, 900 min)
            report($e);

            return response('error', 500);
        }

        // must be exactly "success": no spaces, no HTML
        return response('success', 200)->header('Content-Type', 'text/plain');
    }
}
