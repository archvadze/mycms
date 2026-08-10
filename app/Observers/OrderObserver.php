<?php
namespace App\Observers;

use App\Models\Order;
use App\Models\Project;
use App\Models\Client;
use App\Mail\OrderConfirmationMail;
use App\Mail\NewOrderAdminMail;
use Illuminate\Support\Facades\Mail;

class OrderObserver
{
    public function created(Order $order): void
    {
        Mail::to($order->email)->send(new OrderConfirmationMail($order));
        Mail::to(config('agency.admin_email'))->send(new NewOrderAdminMail($order));
    }

    public function updated(Order $order): void
    {
        if ($order->wasChanged('status') && $order->status === 'accepted') {
            $this->createProjectFromOrder($order);
        }
    }

    private function createProjectFromOrder(Order $order): void
    {
        $client = $order->client;
        if (!$client) {
            $client = Client::firstOrCreate(
                ['email' => $order->email],
                [
                    'name'  => $order->client_name,
                    'email' => $order->email,
                    'phone' => $order->phone,
                ]
            );
        }

        if (! Project::where('order_id', $order->id)->exists()) {
            Project::create([
                'client_id'   => $client->id,
                'order_id'    => $order->id,
                'title'       => ucfirst(str_replace('-', ' ', $order->website_type)) . ' — ' . $order->domain,
                'description' => $order->project_description ?? 'Project created from order #' . $order->id,
                'status'      => 'pending',
                'price'       => $order->price_estimate,
                'deadline'    => now()->addDays(60),
            ]);
        }
    }
}
