<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class AdminAccess
{
    public const SUPER_ADMIN = 'Super Admin';
    public const ADMIN = 'Admin';
    public const EDITOR = 'Editor';
    public const SUPPORT = 'Support';
    public const CLIENT = 'Client';

    public static function canAccessPanel(?User $user = null): bool
    {
        $user ??= auth()->user();

        return $user instanceof User
            && $user->hasRole([
                self::SUPER_ADMIN,
                self::ADMIN,
                self::EDITOR,
                self::SUPPORT,
            ])
            && $user->status === 'active'
            && $user->hasVerifiedEmail();
    }

    public static function canManageUsers(?User $user = null): bool
    {
        return self::hasRole($user, self::SUPER_ADMIN);
    }

    public static function canManageSettings(?User $user = null): bool
    {
        return self::hasRole($user, self::SUPER_ADMIN);
    }

    public static function canManageContent(?User $user = null): bool
    {
        return self::hasAnyRole($user, [
            self::SUPER_ADMIN,
            self::EDITOR,
        ]);
    }

    public static function canManageSystemContent(?User $user = null): bool
    {
        return self::hasRole($user, self::SUPER_ADMIN);
    }

    public static function canManageOrders(?User $user = null): bool
    {
        return self::hasAnyRole($user, [
            self::SUPER_ADMIN,
            self::ADMIN,
        ]);
    }

    public static function canManageProjects(?User $user = null): bool
    {
        return self::canManageOrders($user);
    }

    public static function canManageClients(?User $user = null): bool
    {
        return self::canManageOrders($user);
    }

    public static function canManageInbox(?User $user = null): bool
    {
        return self::hasAnyRole($user, [
            self::SUPER_ADMIN,
            self::ADMIN,
            self::SUPPORT,
        ]);
    }

    public static function canManageSupportContent(?User $user = null): bool
    {
        return self::hasAnyRole($user, [
            self::SUPER_ADMIN,
            self::SUPPORT,
        ]);
    }

    public static function canManageSubscriptions(?User $user = null): bool
    {
        return self::canManageOrders($user);
    }

    public static function canViewAudit(?User $user = null): bool
    {
        return self::hasRole($user, self::SUPER_ADMIN);
    }

    public static function canDeleteUser(User $record, ?User $user = null): bool
    {
        $user ??= auth()->user();

        return self::canManageUsers($user)
            && ! self::isCurrentUser($record, $user);
    }

    public static function canChangeRoles(User $record, array $newRoles, ?User $user = null): bool
    {
        $user ??= auth()->user();

        if (! self::canManageUsers($user)) {
            return false;
        }

        return ! (
            self::isCurrentUser($record, $user)
            && $record->hasRole(self::SUPER_ADMIN)
            && ! in_array(self::SUPER_ADMIN, $newRoles, true)
        );
    }

    public static function assignableRoles(?User $user = null): array
    {
        if (! self::canManageUsers($user)) {
            return [];
        }

        return [
            self::SUPER_ADMIN,
            self::ADMIN,
            self::EDITOR,
            self::SUPPORT,
            self::CLIENT,
        ];
    }

    public static function assignableTeamRoles(?User $user = null): array
    {
        return array_values(array_intersect(
            self::assignableRoles($user),
            [
                self::SUPER_ADMIN,
                self::ADMIN,
                self::EDITOR,
                self::SUPPORT,
            ]
        ));
    }

    public static function isCurrentUser(Model $record, ?User $user = null): bool
    {
        $user ??= auth()->user();

        return $user instanceof User
            && $record instanceof User
            && $record->is($user);
    }

    private static function hasRole(?User $user, string $role): bool
    {
        $user ??= auth()->user();

        return $user instanceof User && $user->hasRole($role);
    }

    private static function hasAnyRole(?User $user, array $roles): bool
    {
        $user ??= auth()->user();

        return $user instanceof User && $user->hasRole($roles);
    }
}
