<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Address extends Model
{
    use HasFactory;

    protected $fillable = [
        'region',
        'province',
        'city',
        'barangay',
        'street',
        'postal_code',
        'latitude',
        'longitude',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:8',
            'longitude' => 'decimal:8',
        ];
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function clinics(): HasMany
    {
        return $this->hasMany(Clinic::class);
    }

    public function getFullAddressAttribute(): string
    {
        return trim("{$this->street}, {$this->barangay}, {$this->city}, {$this->province}, {$this->region}");
    }
}