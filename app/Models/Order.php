<?php
namespace App\Models;

use App\Enums\OrderStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class Order extends Model
{
    use HasFactory, LogsActivity;
    protected $fillable = [
        'client_id',
        'client_name',
        'email',
        'phone',
        'domain',
        'website_type',
        'price_estimate',
        'status',
        'timeline',
        'budget_range',
        'project_description',
        'additional_requirements',
        'payment_status',
        'payment_id',
        'payment_method',
        'paid_at',
    ];
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['status', 'payment_status', 'price_estimate'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->setDescriptionForEvent(fn(string $eventName) => "Order #{$this->id} was {$eventName}");
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'client_id');
    }

    public function services(): BelongsToMany
    {
        return $this->belongsToMany(Service::class, 'order_services');
    }

    public function features(): BelongsToMany
    {
        return $this->belongsToMany(Feature::class, 'order_features');
    }

    public function project(): HasOne
    {
        return $this->hasOne(Project::class);
    }
}
