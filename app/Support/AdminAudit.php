<?php

namespace App\Support;

use App\Models\SiteSetting;
use App\Models\User;

class AdminAudit
{
    public static function logRoleChange(User $user, array $oldRoles, array $newRoles): void
    {
        sort($oldRoles);
        sort($newRoles);

        if ($oldRoles === $newRoles) {
            return;
        }

        activity('security')
            ->causedBy(auth()->user())
            ->performedOn($user)
            ->event('roles_updated')
            ->withProperties([
                'old_roles' => $oldRoles,
                'new_roles' => $newRoles,
            ])
            ->log("Roles updated for {$user->email}");
    }

    public static function logSettingChange(string $key, mixed $oldValue, mixed $newValue): void
    {
        if ((string) $oldValue === (string) $newValue) {
            return;
        }

        $properties = ['setting_key' => $key];

        if (! AuditLogFormatter::isSensitiveKey($key)) {
            $properties['old'] = AuditLogFormatter::safeSettingValue($key, $oldValue);
            $properties['new'] = AuditLogFormatter::safeSettingValue($key, $newValue);
        }

        activity('settings')
            ->causedBy(auth()->user())
            ->event('setting_updated')
            ->withProperties($properties)
            ->log("Site setting {$key} changed");
    }
}
