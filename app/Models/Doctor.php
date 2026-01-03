<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Doctor extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'specialty',
        'sub_specialty',
        'license_number',
        'license_issued_date',
        'license_expiry_date',
        'license_image',
        'signature',
        'bio',
    ];

    protected function casts(): array
    {
        return [
            'license_issued_date' => 'date',
            'license_expiry_date' => 'date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function clinics(): BelongsToMany
    {
        return $this->belongsToMany(Clinic::class, 'doctor_schedules')
            ->withPivot('id')
            ->withTimestamps();
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(DoctorSchedule::class);
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }
}