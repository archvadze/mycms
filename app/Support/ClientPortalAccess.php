<?php

namespace App\Support;

use App\Models\Client;
use App\Models\Project;
use App\Models\ProjectFile;
use Illuminate\Support\Facades\Auth;

class ClientPortalAccess
{
    public function currentClient(): Client
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

        return $user->client()->first() ?? abort(403);
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
