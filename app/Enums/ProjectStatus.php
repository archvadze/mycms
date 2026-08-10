<?php
namespace App\Enums;

enum ProjectStatus: string
{
    case Pending     = 'pending';
    case InProgress  = 'in_progress';
    case Review      = 'review';
    case Completed   = 'completed';

    public function label(): string
    {
        return match($this) {
            self::Pending    => 'Pending',
            self::InProgress => 'In Progress',
            self::Review     => 'Review',
            self::Completed  => 'Completed',
        };
    }

    public function color(): string
    {
        return match($this) {
            self::Pending    => 'warning',
            self::InProgress => 'info',
            self::Review     => 'primary',
            self::Completed  => 'success',
        };
    }
}
