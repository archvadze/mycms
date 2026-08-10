<?php

namespace App\Support;

use Illuminate\Support\Str;

class ClientPortalDisplay
{
    public static function projectStatusLabel(?string $status): string
    {
        return match ($status) {
            'in_progress' => 'In Progress',
            'review' => 'In Review',
            'completed' => 'Completed',
            'pending' => 'Pending',
            default => self::label($status),
        };
    }

    public static function projectStatusBadge(?string $status): string
    {
        return match ($status) {
            'completed' => 'bg-green-100 text-green-800',
            'in_progress' => 'bg-blue-100 text-blue-800',
            'review' => 'bg-yellow-100 text-yellow-800',
            default => 'bg-gray-100 text-gray-800',
        };
    }

    public static function orderStatusLabel(?string $status): string
    {
        return match ($status) {
            'pending' => 'Pending',
            'contacted' => 'Contacted',
            'accepted' => 'Accepted',
            'rejected' => 'Rejected',
            default => self::label($status),
        };
    }

    public static function orderStatusBadge(?string $status): string
    {
        return match ($status) {
            'accepted' => 'bg-green-100 text-green-800',
            'contacted' => 'bg-blue-100 text-blue-800',
            'rejected' => 'bg-red-100 text-red-800',
            default => 'bg-yellow-100 text-yellow-800',
        };
    }

    public static function paymentStatusLabel(?string $status): string
    {
        return self::label($status ?: 'unpaid');
    }

    public static function paymentStatusBadge(?string $status): string
    {
        return $status === 'paid'
            ? 'bg-green-100 text-green-800'
            : 'bg-yellow-100 text-yellow-800';
    }

    public static function subscriptionStatusLabel(?string $status): string
    {
        return self::label($status);
    }

    public static function subscriptionStatusBadge(?string $status): string
    {
        return match ($status) {
            'active' => 'bg-green-100 text-green-800',
            'cancelled' => 'bg-red-100 text-red-800',
            'expired' => 'bg-gray-100 text-gray-800',
            default => 'bg-yellow-100 text-yellow-800',
        };
    }

    public static function safeFilename(?string $path): string
    {
        $filename = basename((string) $path);

        return $filename !== '' ? $filename : 'Project file';
    }

    private static function label(?string $value): string
    {
        return Str::of($value ?: 'unknown')
            ->replace(['_', '-'], ' ')
            ->title()
            ->value();
    }
}
