<?php

namespace App\Models;

use App\Enums\ClinicStatus;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Clinic extends Model
{
    use HasFactory, HasUuid, SoftDeletes;

    protected $fillable = [
        'uuid',
        'clinic_name',
        'clinic_type',
        'owner_name',
        'address_id',
        'phone',
        'email',
        'status',
        'status_remarks',
        'description',
        'proof_of_address',
        'business_registration',
        'dti_permit',
        'owner_valid_id',
    ];

    protected function casts(): array
    {
        return [
            'status' => ClinicStatus::class,
        ];
    }

    public function address(): BelongsTo
    {
        return $this->belongsTo(Address::class);
    }

    public function staff(): HasMany
    {
        return $this->hasMany(ClinicStaff::class);
    }

    public function doctors(): BelongsToMany
    {
        return $this->belongsToMany(Doctor::class, 'doctor_schedules')
            ->withPivot('id')
            ->withTimestamps();
    }

    public function services(): HasMany
    {
        return $this->hasMany(Service::class);
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }

    public function inventory(): HasMany
    {
        return $this->hasMany(Inventory::class);
    }

    public function announcements(): HasMany
    {
        return $this->hasMany(Announcement::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(ClinicImage::class);
    }

    public function ratings(): HasMany
    {
        return $this->hasMany(ClinicRating::class);
    }

    public function hmoProviders(): HasMany
    {
        return $this->hasMany(ClinicHmo::class);
    }

    public function operatingHours(): HasMany
    {
        return $this->hasMany(ClinicOperatingHour::class);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', ClinicStatus::ACTIVE);
    }

    public function scopeApproved($query)
    {
        return $query->where('status', ClinicStatus::APPROVED);
    }
}