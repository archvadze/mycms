<?php

namespace App\Http\Controllers;

use App\Models\DigitalProduct;
use App\Models\Page;
use App\Models\DigitalProductVersion;
use App\Models\Purchase;
use App\Mail\PurchaseConfirmationMail;
use Illuminate\Support\Facades\Mail;
use Illuminate\Http\Request;
use Srmklive\PayPal\Services\PayPal as PayPalClient;

class ShopController extends Controller
{
    public function index(Request $request)
    {
        $query = DigitalProduct::where('is_published', true);

        if ($request->category) {
            $query->where('category', $request->category);
        }

        if ($request->search) {
            $query->where('name', 'like', '%'.$request->search.'%');
        }

        $page = Page::where('slug', 'shop')->first();
        $products = $query->latest()->paginate($page->items_count ?? 12);

        $categories = DigitalProduct::where('is_published', true)
            ->select('category')
            ->distinct()
            ->pluck('category');

        $page = Page::where('slug', 'shop')->first();
        return view('shop.index', compact('products', 'categories', 'page'));
    }

    public function show(string $slug)
    {
        $product = DigitalProduct::where('slug', $slug)
            ->where('is_published', true)
            ->with('versions')
            ->firstOrFail();

        $hasPurchased = false;
        if (auth()->check()) {
            $hasPurchased = Purchase::whereHas('version', function ($q) use ($product) {
                $q->where('digital_product_id', $product->id);
            })->where('user_id', auth()->id())->exists();
        }

        $page = \App\Models\Page::where('slug', 'shop')->first();
        return view('shop.show', compact('product', 'hasPurchased', 'page'));
    }

public function download(Purchase $purchase)
{
    if ($purchase->user_id !== auth()->id()) {
        abort(403);
    }

    if ($purchase->download_limit <= 0) {
        return redirect()->back()->with('error', 'Download limit reached.');
    }

    if ($purchase->download_expires_at && $purchase->download_expires_at->isPast()) {
        return redirect()->back()->with('error', 'Download access has expired.');
    }

    $filePath = storage_path('app/public/' . $purchase->version->file_path);

    if (!file_exists($filePath)) {
        return redirect()->back()->with('error', 'File not found. Please contact support.');
    }

    // Log download
    \App\Models\DownloadLog::create([
        'purchase_id'    => $purchase->id,
        'user_id'        => auth()->id(),
        'product_name'   => $purchase->version->product->name,
        'version_number' => $purchase->version->version_number,
        'ip_address'     => request()->ip(),
    ]);

    $purchase->decrement('download_limit');

    $extension = pathinfo($purchase->version->file_path, PATHINFO_EXTENSION);

$safeProductName = preg_replace(
    '/[^A-Za-z0-9_-]+/',
    '_',
    $purchase->version->product->name
);

$downloadName = $safeProductName
    . '_v'
    . $purchase->version->version_number
    . ($extension ? '.' . $extension : '');

return response()->download($filePath, $downloadName);
}
public function checkout(string $slug)
{
    $product = DigitalProduct::where('slug', $slug)
        ->where('is_published', true)
        ->with(['versions' => fn($q) => $q->where('is_active', true)->latest()])
        ->firstOrFail();

    // Already purchased?
    $hasPurchased = Purchase::whereHas('version', function ($q) use ($product) {
        $q->where('digital_product_id', $product->id);
    })->where('user_id', auth()->id())->exists();

    if ($hasPurchased) {
        return redirect()->route('shop.show', $slug)->with('info', 'You already own this product.');
    }

    $version = $product->versions->first();
    if (!$version) {
        return redirect()->route('shop.show', $slug)->with('error', 'No active version available.');
    }

    $provider = new PayPalClient;
    $provider->setApiCredentials(config('paypal'));
    $token = @$provider->getAccessToken();
    $provider->setAccessToken($token);

    $orderData = [
        'intent' => 'CAPTURE',
        'purchase_units' => [[
            'reference_id' => 'PRODUCT-' . $product->id,
            'description'  => $product->name,
            'amount'       => [
                'currency_code' => 'USD',
                'value'         => number_format($product->price, 2, '.', ''),
            ],
        ]],
        'application_context' => [
            'return_url' => route('shop.checkout.success', $slug),
            'cancel_url' => route('shop.checkout.cancel', $slug),
            'brand_name' => config('app.name'),
            'user_action' => 'PAY_NOW',
        ],
    ];

    $response = $provider->createOrder($orderData);

    if (isset($response['id']) && $response['status'] === 'CREATED') {
        // Store version_id in session
        session(['shop_version_id' => $version->id]);
        foreach ($response['links'] as $link) {
            if ($link['rel'] === 'approve') {
                return redirect($link['href']);
            }
        }
    }

    return back()->with('error', 'Payment failed. Please try again.');
}

public function checkoutSuccess(Request $request, string $slug)
{
    $product = DigitalProduct::where('slug', $slug)->firstOrFail();
    $versionId = session('shop_version_id');

    if (!$versionId || !$request->token) {
        return redirect()->route('shop.show', $slug)->with('error', 'Invalid session.');
    }

    $provider = new PayPalClient;
    $provider->setApiCredentials(config('paypal'));
    $token = @$provider->getAccessToken();
    $provider->setAccessToken($token);

    $response = $provider->capturePaymentOrder($request->token);

    if (isset($response['status']) && $response['status'] === 'COMPLETED') {
        $purchase = Purchase::create([
            'user_id'                    => auth()->id(),
            'digital_product_version_id' => $versionId,
            'transaction_id'             => $response['id'],
            'amount'                     => $product->price,
            'download_limit'             => 5,
            'download_expires_at'        => now()->addYear(),
        ]);

// Send confirmation email
Mail::to(auth()->user()->email)
    ->send(new PurchaseConfirmationMail(
        $purchase->load('version.product', 'user')
    ));

        session()->forget('shop_version_id');

        return redirect()->route('shop.show', $slug)
            ->with('success', 'Purchase completed! You can now download the product.');
    }

    return redirect()->route('shop.show', $slug)->with('error', 'Payment could not be completed.');
}

public function checkoutCancel(string $slug)
{
    return redirect()->route('shop.show', $slug)->with('warning', 'Payment was cancelled.');
}
}
