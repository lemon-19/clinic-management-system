<?php

// ============================================
// app/Models/Inventory.php
// ============================================

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Inventory extends Model
{
    use HasFactory;

    protected $fillable = [
        'clinic_id',
        'name',
        'brand',
        'category',
        'quantity',
        'unit',
        'critical_level',
        'expiry_date',
        'batch_number',
        'cost_price',
        'selling_price',
        'supplier',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'critical_level' => 'integer',
            'expiry_date' => 'date',
            'cost_price' => 'decimal:2',
            'selling_price' => 'decimal:2',
        ];
    }

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(InventoryTransaction::class);
    }

    public function getIsCriticalAttribute(): bool
    {
        return $this->quantity <= $this->critical_level;
    }

    public function getIsExpiringSoonAttribute(): bool
    {
        return $this->expiry_date && $this->expiry_date->diffInDays(now()) <= 30;
    }

    public function scopeLowStock($query)
    {
        return $query->whereRaw('quantity <= critical_level');
    }

    public function scopeExpiringSoon($query, $days = 30)
    {
        return $query->where('expiry_date', '<=', now()->addDays($days));
    }
}