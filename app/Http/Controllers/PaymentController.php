<?php
namespace App\Http\Controllers;

use App\Support\ClientPortalAccess;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Srmklive\PayPal\Services\PayPal as PayPalClient;

class PaymentController extends Controller
{
    public function __construct(private ClientPortalAccess $portalAccess) {}

    public function createPayment(Request $request, $orderId)
    {
        $order = $this->portalAccess->ownedOrderOrFail($orderId);

        $provider = app(PayPalClient::class);
        $provider->setApiCredentials(config('paypal'));
        $token = @$provider->getAccessToken();
        $provider->setAccessToken($token);

        $orderData = [
            'intent' => 'CAPTURE',
            'purchase_units' => [
                [
                    'reference_id' => 'ORDER-' . $order->id,
                    'description'  => 'Web Development Services - ' . $order->domain,
                    'amount'       => [
                        'currency_code' => 'USD',
                        'value'         => number_format($order->price_estimate, 2, '.', ''),
                    ],
                ],
            ],
            'application_context' => [
                'return_url' => route('payment.success', $order->id),
                'cancel_url' => route('payment.cancel', $order->id),
                'brand_name' => config('app.name'),
                'user_action' => 'PAY_NOW',
            ],
        ];

        $response = $provider->createOrder($orderData);

        Log::info('PayPal create order completed.', [
            'operation' => 'create_order',
            'order_id' => $order->id,
            'provider_status' => $response['status'] ?? null,
            'provider_order_id' => $response['id'] ?? null,
        ]);

        if (isset($response['id']) && $response['status'] === 'CREATED') {
            foreach ($response['links'] as $link) {
                if ($link['rel'] === 'approve') {
                    return redirect($link['href']);
                }
            }
        }

        Log::warning('PayPal create order failed.', [
            'operation' => 'create_order',
            'order_id' => $order->id,
            'provider_status' => $response['status'] ?? null,
            'provider_order_id' => $response['id'] ?? null,
        ]);

        return back()->with('error', 'Payment could not be started. Please try again.');
    }

public function paymentSuccess(Request $request, $orderId)
{
    $order = $this->portalAccess->ownedOrderOrFail($orderId);

    if ($order->payment_status === 'paid' && filled($order->payment_id)) {
        return redirect()->route('order.success', $order->id)
            ->with('success', 'Payment completed successfully!');
    }

    $provider = app(PayPalClient::class);
    $provider->setApiCredentials(config('paypal'));
    $token = @$provider->getAccessToken();
    $provider->setAccessToken($token);

    $response = $provider->capturePaymentOrder($request->token);

    if ($this->captureMatchesOrder($response, $order)) {
        $order->update([
            'status'         => 'accepted',
            'payment_status' => 'paid',
            'payment_id'     => $response['id'],
            'paid_at'        => now(),
        ]);

        return redirect()->route('order.success', $order->id)
            ->with('success', 'Payment completed successfully!');
    }

    Log::warning('PayPal capture did not match local order.', [
        'operation' => 'capture_order',
        'order_id' => $order->id,
        'provider_status' => $response['status'] ?? null,
        'provider_order_id' => $response['id'] ?? null,
    ]);

    return redirect()->route('payment.cancel', $orderId)
        ->with('error', 'Payment could not be completed.');
}

public function paymentCancel($orderId)
{
    $this->portalAccess->ownedOrderOrFail($orderId);

    return redirect()->route('order.success', $orderId)
        ->with('warning', 'Payment was cancelled. You can try again later.');
}

public static function captureMatchesOrder(array $response, Order $order): bool
{
    if (($response['status'] ?? null) !== 'COMPLETED') {
        return false;
    }

    $expectedReference = 'ORDER-' . $order->id;
    $expectedAmount = number_format($order->price_estimate, 2, '.', '');

    foreach (($response['purchase_units'] ?? []) as $unit) {
        $reference = $unit['reference_id'] ?? null;
        $amount = $unit['payments']['captures'][0]['amount'] ?? $unit['amount'] ?? [];

        if (
            $reference === $expectedReference
            && ($amount['currency_code'] ?? null) === 'USD'
            && (string) ($amount['value'] ?? '') === $expectedAmount
        ) {
            return true;
        }
    }

    return false;
}
}
