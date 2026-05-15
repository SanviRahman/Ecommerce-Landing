<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class User extends Authenticatable implements HasMedia
{
    use HasFactory, Notifiable, SoftDeletes, InteractsWithMedia;

    public const ROLE_ADMIN    = 'admin';
    public const ROLE_EMPLOYEE = 'employee';

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'is_active',
        'email_verified_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $appends = [
        'image_url',
        'role_text',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'is_active'         => 'boolean',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Spatie Media Library
    |--------------------------------------------------------------------------
    */

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('avatars')
            ->useDisk('public')
            ->singleFile();
    }

    public function getImageUrlAttribute(): string
    {
        $media = $this->getFirstMedia('avatars');

        if ($media) {
            return $media->getUrl();
        }

        return asset('vendor/adminlte/dist/img/user2-160x160.jpg');
    }

    /*
    |--------------------------------------------------------------------------
    | AdminLTE User Menu
    |--------------------------------------------------------------------------
    */

    public function adminlte_image(): string
    {
        return $this->image_url;
    }

    public function adminlte_desc(): string
    {
        return $this->email;
    }

    public function adminlte_profile_url(): string
    {
        return route('admin.profile');
    }

    /*
    |--------------------------------------------------------------------------
    | Accessors
    |--------------------------------------------------------------------------
    */

    public function getRoleTextAttribute(): string
    {
        return match ($this->role) {
            self::ROLE_ADMIN    => 'Admin',
            self::ROLE_EMPLOYEE => 'Employee',
            default             => ucfirst((string) $this->role),
        };
    }

    /*
    |--------------------------------------------------------------------------
    | Role Helpers
    |--------------------------------------------------------------------------
    */

    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    public function isEmployee(): bool
    {
        return $this->role === self::ROLE_EMPLOYEE;
    }

    /*
    |--------------------------------------------------------------------------
    | Scopes
    |--------------------------------------------------------------------------
    */

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeAdmins(Builder $query): Builder
    {
        return $query->where('role', self::ROLE_ADMIN);
    }

    public function scopeEmployees(Builder $query): Builder
    {
        return $query->where('role', self::ROLE_EMPLOYEE);
    }

    public function scopeActiveEmployees(Builder $query): Builder
    {
        return $query->where('role', self::ROLE_EMPLOYEE)
            ->where('is_active', true);
    }

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function assignedOrders(): HasMany
    {
        return $this->hasMany(Order::class, 'assigned_employee_id');
    }

    /**
     * Active assigned orders used for employee workload calculation.
     *
     * Important:
     * Order status must be lowercase because orders table default is "pending"
     * and OrderController also uses lowercase statuses.
     */
    public function activeAssignedOrders(): HasMany
    {
        return $this->hasMany(Order::class, 'assigned_employee_id')
            ->where('is_fake', false)
            ->whereIn('order_status', [
                Order::STATUS_PENDING,
                Order::STATUS_CONFIRMED,
                Order::STATUS_PROCESSING,
                Order::STATUS_SHIPPED,
            ]);
    }

    public function orderStatusLogs(): HasMany
    {
        return $this->hasMany(OrderStatusLog::class, 'created_by');
    }
}
