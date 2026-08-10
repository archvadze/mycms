<?php

namespace App\Support;

use App\Models\Client;
use App\Models\Order;
use App\Models\Project;
use App\Models\ProjectFile;
use App\Models\Purchase;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

class ClientPortalAccess
{
    public function currentClientUser(): User
    {
        $user = Auth::user();

        if (
            ! $user
            || ! $user->hasRole('Client')
            || $user->status !== 'active'
            || ! $user->hasVerifiedEmail()
        ) {
            abort(403);
        }

        return $user;
    }

    public function currentClient(): Client
    {
        $user = $this->currentClientUser();

        return $user->client()->first() ?? abort(403);
    }

    public function ownedOrderOrFail(int|string $orderId): Order
    {
        return $this->currentClient()
            ->orders()
            ->with(['services', 'features', 'project'])
            ->findOrFail($orderId);
    }

    public function ownedPurchaseOrFail(int|string $purchaseId): Purchase
    {
        return Purchase::query()
            ->with(['version.product'])
            ->where('user_id', $this->currentClientUser()->id)
            ->findOrFail($purchaseId);
    }

    public function ownedSubscriptionOrFail(int|string $subscriptionId): Subscription
    {
        return Subscription::query()
            ->where('user_id', $this->currentClientUser()->id)
            ->findOrFail($subscriptionId);
    }

    public function ownedProjectOrFail(int|string $projectId): Project
    {
        return $this->currentClient()
            ->projects()
            ->findOrFail($projectId);
    }

    public function ownedProjectFileOrFail(int|string $projectId, int|string $fileId): array
    {
        $project = $this->ownedProjectOrFail($projectId);
        $file = $project->files()->findOrFail($fileId);

        return [$project, $file];
    }
}
