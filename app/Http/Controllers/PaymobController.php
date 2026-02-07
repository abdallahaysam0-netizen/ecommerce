<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

/**
 * PaymobController
 * 
 * This controller is required by PaymobServiceProvider from vendor package.
 * The actual payment processing is handled by PaymentController and PaymobWebhookController.
 */
class PaymobController extends Controller
{
    /**
     * Process Paymob Payment
     * 
     * Note: This method is registered by PaymobServiceProvider but not actively used.
     * Payment processing is handled via /api/payments/pay/{order} endpoint.
     */
    public function process()
    {
        return response()->json([
            'success' => false,
            'message' => 'This endpoint is deprecated. Please use /api/payments/pay/{order} instead.'
        ], 404);
    }

    /**
     * Paymob Callback
     * 
     * Note: This method is registered by PaymobServiceProvider but not actively used.
     * Webhook handling is done via /api/paymob/webhook endpoint.
     */
    public function callback(Request $request)
    {
        return response()->json([
            'success' => false,
            'message' => 'This endpoint is deprecated. Webhooks are handled via /api/paymob/webhook endpoint.'
        ], 404);
    }
}
