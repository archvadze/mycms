<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Enums\ProjectStatus;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class Project extends Model
{
    use HasFactory, LogsActivity;
    protected $fillable = [
        'client_id',
        'order_id',
        'title',
        'description',
        'status',
        'progress',
        'price',
        'deadline',
    ];
    protected $casts = [
        'deadline' => 'date',
        'progress' => 'integer',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'client_id',
                'order_id',
                'title',
                'status',
                'progress',
                'price',
                'deadline',
            ])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->setDescriptionForEvent(
                fn(string $eventName): string => "Project #{$this->id} was {$eventName}"
            );
    }
    public function order(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Models\Order::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }
    public function tasks(): HasMany
    {
        return $this->hasMany(ProjectTask::class);
    }
    public function messages(): HasMany
    {
        return $this->hasMany(ProjectMessage::class);
    }
    public function files(): HasMany
    {
        return $this->hasMany(ProjectFile::class);
    }
    public function getProgressColorAttribute(): string
    {
        return match(true) {
            $this->progress >= 100 => 'bg-green-500',
            $this->progress >= 60  => 'bg-blue-500',
            $this->progress >= 30  => 'bg-yellow-500',
            default                => 'bg-gray-400',
        };
    }
}
