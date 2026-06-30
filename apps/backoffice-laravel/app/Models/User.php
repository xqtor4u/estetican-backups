<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use App\Models\Operator;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\Activitylog\Models\Concerns\CausesActivity;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Permission\Traits\HasRoles;


#[Fillable([
    'name',
    'first_name',
    'last_name',
    'email',
    'password',
    'ine_number',
    'imss_number',
    'address',
    'phone',
    'profile_photo_path',
    'emergency_contact_name',
    'emergency_contact_phone',
    'hire_date',
    'role',
    'is_active',
    'can_login',
    'is_operator',
    'operator_code',
    'operator_role_id',
    'operator_id',
    'notes',
])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, HasRoles, LogsActivity, CausesActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'first_name', 'last_name', 'email', 'phone',
                       'is_active', 'can_login', 'is_operator', 'operator_role_id',
                       'role', 'hire_date', 'notes'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('usuarios');
    }


    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'can_login' => 'boolean',
            'is_active' => 'boolean',
            'is_operator' => 'boolean',
            'hire_date' => 'date',
        ];
    }

    /**
     * Relación con el rol operativo (April 14 Fusion)
     */
    public function operatorRole()
    {
        return $this->belongsTo(OperatorRole::class, 'operator_role_id');
    }

    public function operator()
    {
        return $this->belongsTo(Operator::class, 'operator_id');
    }

    // Determina si el usuario es super admin (Híbrido Spatie + Campo legacy)
    public function getIsSuperAdminAttribute()
    {
        return $this->role === 'admin' || 
               $this->hasRole('admin') || 
               $this->hasRole('super-admin');
    }

}
