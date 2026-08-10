<?php
namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;
use Filament\Models\Contracts\FilamentUser;
use App\Enums\UserStatus;
use App\Support\AdminAccess;

class User extends Authenticatable implements \Illuminate\Contracts\Auth\MustVerifyEmail, FilamentUser
{
    use HasFactory, Notifiable, HasRoles, LogsActivity;

    protected $fillable = [
        'name', 'email', 'password', 'status',
        'email_verified_at', 'avatar', 'tags',
    ];

    protected $hidden = [
        'password', 'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password'          => 'hashed',
            'tags'              => 'array',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'email', 'status'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->setDescriptionForEvent(fn(string $eventName) => "User {$this->email} was {$eventName}");
    }

    public function getAvatarUrlAttribute(): string
    {
        if ($this->avatar) {
            return asset('avatars/' . $this->avatar);
        }
        return asset('avatars/default.svg');
    }

    public function getMemberSinceAttribute(): string
    {
        return $this->created_at->format('Y');
    }

    public function client(): HasOne
    {
        return $this->hasOne(Client::class);
    }

    public function canAccessPanel(\Filament\Panel $panel): bool
    {
        return AdminAccess::canAccessPanel($this);
    }

    public function isActive(): bool { return $this->status === 'active'; }
    public function isBlocked(): bool { return $this->status === 'blocked'; }
    public function isPending(): bool { return $this->status === 'pending'; }
}
