<?php
namespace App\Http\Controllers;

use App\Mail\NewSubscriptionRequestMail;
use App\Mail\SubscriptionCancelAdminMail;
use App\Mail\SubscriptionCancelRequestMail;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Support\ClientPortalAccess;
use Illuminate\Support\Facades\Mail;

class SubscriptionController extends Controller
{
    public function __construct(private ClientPortalAccess $portalAccess) {}

    public function plans()
    {
        $user = $this->portalAccess->currentClientUser();

        $plans = SubscriptionPlan::where('is_active', true)
            ->orderBy('sort')
            ->get();

        $current = Subscription::where('user_id', $user->id)
            ->whereIn('status', ['active', 'pending'])
            ->with('plan')
            ->first();

        return view('subscription.plans', compact('plans', 'current'));
    }

    public function request(SubscriptionPlan $plan)
    {
        $user = $this->portalAccess->currentClientUser();

        abort_unless($plan->is_active, 404);

        $existing = Subscription::where('user_id', $user->id)
            ->whereIn('status', ['active', 'pending'])
            ->first();

        if ($existing) {
            return back()->with('error', 'You already have an active subscription.');
        }

        $sub = Subscription::create([
            'user_id'              => $user->id,
            'subscription_plan_id' => $plan->id,
            'status'               => 'pending',
        ]);

        $sub->load(['user', 'plan']);

        Mail::to(config('agency.admin_email'))
            ->send(new NewSubscriptionRequestMail($sub));

        return redirect()->route('client-dashboard.index')
            ->with('success', 'Subscription request sent! We will activate it shortly.');
    }

    public function cancel()
    {
        $user = $this->portalAccess->currentClientUser();

        $sub = Subscription::where('user_id', $user->id)
            ->where('status', 'active')
            ->first();

        if ($sub) {
            $sub->update(['cancel_requested' => true]);
            $sub->load(['user', 'plan']);

            Mail::to($sub->user->email)
                ->send(new SubscriptionCancelRequestMail($sub));

            Mail::to(config('agency.admin_email'))
                ->send(new SubscriptionCancelAdminMail($sub));
        }

        return back()->with('success', 'Cancellation requested. We will contact you shortly.');
    }
}
