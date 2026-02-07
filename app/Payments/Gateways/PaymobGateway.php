<?php

namespace App\Payments\Gateways;

use App\Models\Order;
use App\Models\Payment;
use App\Payments\Contracts\PaymentGateway;
use App\Payments\DTO\PaymentResult;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PaymobGateway implements PaymentGateway
{
    // القاعدة الذهبية: توحيد الرابط لـ egypt لضمان توافق التوقيع الرقمي
    private string $baseUrl = 'https://accept.paymob.com/api';

    public function authorize(Payment $payment, string $method = 'card'): PaymentResult
    {
        try {
            // 1️⃣ الحصول على الـ Auth Token
            $token = $this->getAuthToken();

            // 2️⃣ إنشاء طلب (Order) على Paymob
            $paymobOrderId = $this->createPaymobOrder($token, $payment);

            if ($payment->order_id) {
                Order::where('id', $payment->order_id)->update(['paymob_id' => $paymobOrderId]);
            }

            // 3️⃣ تحديد الـ Integration ID بناءً على الطريقة
            $integrationId = $this->getIntegrationId($method);

            // 4️⃣ إنشاء الـ Payment Key
            $paymentKey = $this->getPaymentKey($token, $paymobOrderId, $payment, $integrationId);

            // 5️⃣ تنفيذ الخطوة النهائية (توليد الرابط أو الكود)
            return $this->processFinalStep($paymentKey, $method, $payment);

        } catch (\Throwable $e) {
            Log::error('Paymob Gateway Error: ' . $e->getMessage());
            return new PaymentResult(false, $e->getMessage());
        }
    }

    public function refund(string $transactionId, int $amount): bool
    {
        try {
            $token = $this->getAuthToken();
            $response = Http::post("{$this->baseUrl}/acceptance/void_refund/refund", [
                'auth_token'     => $token,
                'transaction_id' => $transactionId,
                'amount_cents'   => (int) round($amount * 100),
            ]);

            return $response->successful() && $response->json('success') === true;
        } catch (\Throwable $e) {
            Log::error('Paymob Refund Failed: ' . $e->getMessage());
            return false;
        }
    }

    // --- الدوال المساعدة بعد توحيد الروابط ---

    private function getAuthToken(): string
    {
        $response = Http::post("{$this->baseUrl}/auth/tokens", [
            'api_key' => config('services.paymob.api_key'),
        ]);

        if (!$response->successful()) throw new \Exception('Paymob Auth Failed: ' . $response->body());
        return $response->json('token');
    }

    private function createPaymobOrder(string $token, Payment $payment): int
    {
        $response = Http::post("{$this->baseUrl}/ecommerce/orders", [
            'auth_token' => $token,
            'delivery_needed' => false,
            'amount_cents' => (int) round($payment->amount * 100),
            'currency' => 'EGP',
            'merchant_order_id' => (string) $payment->id,
            'items' => [],
        ]);

        if (!$response->successful()) throw new \Exception('Paymob Order Creation Failed: ' . $response->body());
        return $response->json('id');
    }

    private function getPaymentKey(string $token, int $paymobOrderId, Payment $payment, int $integrationId): string
    {
        $shipping = $payment->order ? $payment->order->toArray() : ($payment->metadata['shipping'] ?? []);
        $names = $this->splitName($shipping['shipping_name'] ?? 'Customer Guest');

        $response = Http::post("{$this->baseUrl}/acceptance/payment_keys", [
            'auth_token'   => $token,
            'amount_cents' => (int) round($payment->amount * 100),
            'expiration'   => 3600,
            'order_id'     => $paymobOrderId,
            'currency'     => 'EGP',
            'integration_id' => $integrationId,
            'billing_data' => [
                'first_name'   => $names['first'],
                'last_name'    => $names['last'],
                'email'        => $payment->user->email ?? 'customer@myshop.com',
                'phone_number' => preg_replace('/[^0-9]/', '', $shipping['shipping_phone'] ?? '01000000000'),
                'apartment'    => 'NA', 'floor' => 'NA', 'building' => 'NA', 'shipping_method' => 'PKG',
                'street'       => $shipping['shipping_address'] ?? 'NA',
                'city'         => $shipping['shipping_city'] ?? 'Cairo',
                'postal_code'  => $shipping['shipping_zipcode'] ?? '00000',
                'country'      => 'EG',
                'state'        => $shipping['shipping_state'] ?? 'Cairo',
            ],
        ]);

        if (!$response->successful()) throw new \Exception('Paymob Payment Key Failed: ' . $response->body());
        return $response->json('token');
    }

    private function processFinalStep(string $paymentKey, string $method, Payment $payment): PaymentResult
    {
        if ($method === 'credit_card' || $method === 'card') {
            $iframeId = config('services.paymob.iframe_id');
            return new PaymentResult(true, 'Card initiated', [
                'payment_type' => 'card',
                'iframe_url' => "{$this->baseUrl}/acceptance/iframes/{$iframeId}?payment_token={$paymentKey}"
            ]);
        }

        $phone = $payment->order ? $payment->order->shipping_phone : ($payment->metadata['shipping']['shipping_phone'] ?? null);
        $cleanPhone = preg_replace('/[^0-9]/', '', $phone);

        $source = match($method) {
            'fawry'  => ['identifier' => 'AGGREGATOR', 'subtype' => 'AGGREGATOR'],
            'wallet' => ['identifier' => $cleanPhone, 'subtype' => 'WALLET'],
            default  => throw new \Exception('Unsupported payment method'),
        };

        $response = Http::post("{$this->baseUrl}/acceptance/payments/pay", [
            'source' => $source,
            'payment_token' => $paymentKey,
        ]);

        if (!$response->successful()) {
            Log::error("Paymob {$method} execution failed", ['response' => $response->json()]);
            throw new \Exception("Paymob {$method} payment failed: " . ($response->json()['detail'] ?? 'Unknown Error'));
        }

        $data = $response->json();

        return new PaymentResult(true, "{$method} initiated", [
            'payment_type'   => $method,
            'bill_reference' => $data['data']['bill_reference'] ?? null,
            'redirect_url'   => $data['redirect_url'] ?? $data['iframe_redirection_url'] ?? null,
        ]);
    }

    private function getIntegrationId(string $method): int
    {
        return match($method) {
            'fawry'  => (int) config('services.paymob.fawry_integration_id'),
            'wallet' => (int) config('services.paymob.wallet_integration_id'),
            default  => (int) config('services.paymob.card_integration_id'),
        };
    }

    private function splitName(string $name): array
    {
        $parts = explode(' ', trim($name));
        return [
            'first' => $parts[0] ?? 'Customer',
            'last'  => $parts[1] ?? ($parts[0] ?? 'User')
        ];
    }
}