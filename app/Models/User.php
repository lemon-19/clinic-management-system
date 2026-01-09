<?php

namespace App\Models;

use App\Enums\Gender;
use App\Enums\UserType;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, HasUuid, Notifiable, SoftDeletes, HasRoles;
    protected $fillable = [
        'uuid',
        'email',
        'username',
        'password',
        'user_type',
        'first_name',
        'middle_name',
        'last_name',
        'suffix_name',
        'gender',
        'address_id',
        'phone',
        'civil_status',
        'blood_type',
        'date_of_birth',
        'user_image',
        'status',
        'emergency_contact_name',
        'emergency_contact_phone',
        'emergency_contact_relationship',
        'google_id',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected $appends = ['full_name'];

    protected function casts(): array
    {
        return [
            'user_type' => UserType::class,
            'gender' => Gender::class,
            'date_of_birth' => 'date',
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function getFullNameAttribute(): string
    {
        return implode(' ', array_filter([
            $this->first_name,
            $this->middle_name,
            $this->last_name,
            $this->suffix_name,
        ]));
    }

    // Relationships
    public function address(): BelongsTo
    {
        return $this->belongsTo(Address::class);
    }

    public function doctor(): HasOne
    {
        return $this->hasOne(Doctor::class);
    }

    public function clinicStaff(): HasMany
    {
        return $this->hasMany(ClinicStaff::class);
    }

    public function hmoProviders(): HasMany
    {
        return $this->hasMany(UserHmo::class);
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class, 'patient_id');
    }

    public function medicalRecords(): HasMany
    {
        return $this->hasMany(MedicalRecord::class, 'patient_id');
    }

    public function ratings(): HasMany
    {
        return $this->hasMany(ClinicRating::class, 'patient_id');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeByType($query, UserType $type)
    {
        return $query->where('user_type', $type);
    }
}