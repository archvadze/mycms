<?php
namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\Request;
use Srmklive\PayPal\Services\PayPal as PayPalClient;

class PaymentController extends Controller
{
    public function createPayment(Request $request, $orderId)
    {
        $order = Order::with('services', 'features')
             ->whereHas('client', function ($query) {
           $query->where('user_id', auth()->id());
             })
          ->findOrFail($orderId);

        $provider = new PayPalClient;
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

        \Log::info('PayPal Response:', $response);

        if (isset($response['id']) && $response['status'] === 'CREATED') {
            foreach ($response['links'] as $link) {
                if ($link['rel'] === 'approve') {
                    return redirect($link['href']);
                }
            }
        }

        \Log::error('PayPal Error:', $response);
        return back()->with('error', 'Payment failed: ' . json_encode($response));
    }

public function paymentSuccess(Request $request, $orderId)
{
    $order = Order::where('id', $orderId)
        ->whereHas('client', function ($query) {
            $query->where('user_id', auth()->id());
        })
        ->firstOrFail();

    $provider = new PayPalClient;
    $provider->setApiCredentials(config('paypal'));
    $token = @$provider->getAccessToken();
    $provider->setAccessToken($token);

    $response = $provider->capturePaymentOrder($request->token);

    if (isset($response['status']) && $response['status'] === 'COMPLETED') {
        $order->update([
            'status'         => 'accepted',
            'payment_status' => 'paid',
            'payment_id'     => $response['id'],
            'paid_at'        => now(),
        ]);

        return redirect()->route('order.success', $order->id)
            ->with('success', 'Payment completed successfully!');
    }

    return redirect()->route('payment.cancel', $orderId)
        ->with('error', 'Payment could not be completed.');
}

public function paymentCancel($orderId)
{
    Order::where('id', $orderId)
        ->whereHas('client', function ($query) {
            $query->where('user_id', auth()->id());
        })
        ->firstOrFail();

    return redirect()->route('order.success', $orderId)
        ->with('warning', 'Payment was cancelled. You can try again later.');
}
}
