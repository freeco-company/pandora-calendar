<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Services\Subscription\EcpayClient;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class EcpayNotifyController extends Controller
{
    public function handle(Request $request): Response
    {
        try {
            EcpayClient::fromConfig()->handleNotify($request->all());
        } catch (\Throwable $e) {
            \Log::warning('ecpay-notify-failed', ['err' => $e->getMessage()]);

            return response('0|FAIL');
        }

        return response('1|OK');
    }
}
