<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use RuntimeException;

class DigitalProductVersion extends Model
{
    use HasFactory;

protected $fillable = [
    'digital_product_id',
    'version_number',  // ← version კი არა
    'changelog',
    'file_path',
    'is_active',
];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    protected static function booted()
    {
        static::deleting(function (DigitalProductVersion $version): void {
            if ($version->purchases()->exists()) {
                throw new RuntimeException('Cannot delete a purchased digital product version.');
            }
        });
    }

    public function product()
    {
        return $this->belongsTo(DigitalProduct::class, 'digital_product_id');
    }

    public function purchases()
    {
        return $this->hasMany(Purchase::class, 'digital_product_version_id');
    }
}
