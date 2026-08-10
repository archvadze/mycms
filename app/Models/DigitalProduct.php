<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use RuntimeException;
use Illuminate\Support\Str;

class DigitalProduct extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'short_description',
        'description',
        'category',
        'tags',
        'price',
        'sale_price',
        'sale_ends_at',
        'image',
        'gallery_images',
        'demo_url',
        'is_published',
        'is_featured',
        'user_id',
    ];

    protected $casts = [
        'tags' => 'array',
        'gallery_images' => 'array',
        'is_published' => 'boolean',
        'is_featured' => 'boolean',
    ];

    protected static function booted()
    {
        static::creating(function ($product) {
            if (empty($product->slug)) {
                $product->slug = Str::slug($product->name);
            }
        });

        static::deleting(function ($product) {
            if ($product->versions()->whereHas('purchases')->exists()) {
                throw new RuntimeException('Cannot delete a digital product with purchased versions.');
            }

            if ($product->image) {
                \Storage::disk('public')->delete($product->image);
            }

            if ($product->gallery_images) {
                foreach ($product->gallery_images as $image) {
                    \Storage::disk('public')->delete($image);
                }
            }
        });
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function versions()
    {
        return $this->hasMany(DigitalProductVersion::class);
    }

    public function purchases()
    {
        return $this->hasMany(Purchase::class);
    }
}
