<?php
namespace App\Http\Controllers;

use App\Services\OrderService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class OrderController extends Controller
{
    public function __construct(
        private OrderService $orderService
    ) {}

    public function create()
    {
        $services = $this->orderService->getAvailableServices();
        $features = $this->orderService->getAvailableFeatures();
        return view('order.create', compact('services', 'features'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'client_name'             => 'required|string|max:255',
            'email'                   => 'required|email|max:255',
            'phone'                   => 'nullable|string|max:20',
            'domain'                  => 'nullable|string|max:255',
            'website_type'            => 'required|string|max:255',
            'timeline'                => 'required|string|max:255',
            'budget_range'            => 'required|string|max:255',
            'services'                => 'required|array|min:1',
            'services.*'              => 'exists:services,id',
            'features'                => 'nullable|array',
            'features.*'              => 'exists:features,id',
            'project_description'     => 'required|string|max:2000',
            'additional_requirements' => 'nullable|string|max:2000',
        ]);

        $clientId = null;
        if (Auth::check() && Auth::user()->hasRole('Client') && Auth::user()->client) {
            $clientId = Auth::user()->client->id;
        }

        try {
            $order = $this->orderService->createOrder($validated, $clientId);
            session(['order_success_id' => $order->id]);
            return redirect()->route('order.success', $order->id)
                ->with('success', 'Your order has been submitted successfully!');
        } catch (\Exception $e) {
            return back()->withInput()->with('error', 'Failed to submit order. Please try again.');
        }
    }

    public function success($orderId)
    {
          $order = \App\Models\Order::where('id', $orderId)
            ->whereHas('client', function ($query) {
          $query->where('user_id', auth()->id());
            })
            ->firstOrFail();

         session()->forget('order_success_id');

         return view('order.success', compact('order'));
    }
}
