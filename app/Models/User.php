<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'mobile', 'password', 'role', 'is_active', 'permissions'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isStaff(): bool
    {
        return $this->role === 'staff';
    }

    public function canAccessAdmin(): bool
    {
        return ($this->is_active ?? true) && ($this->isAdmin() || $this->isStaff());
    }

    public function hasPermission(string $permission): bool
    {
        return $this->isAdmin() || ($this->isStaff() && in_array($permission, $this->permissions ?? [], true));
    }

    /** @return array<string, string> */
    public static function permissionLabels(): array
    {
        return [
            'dashboard.view' => 'View Dashboard',
            'homepage.manage' => 'Manage Homepage',
            'products.manage' => 'Manage Products',
            'categories.manage' => 'Manage Categories',
            'inventory.manage' => 'Manage Inventory',
            'purchases.manage' => 'Manage Purchases & Suppliers',
            'orders.manage' => 'Manage Orders',
            'customers.view' => 'View Customers',
            'payments.manage' => 'Manage Payments',
            'coupons.manage' => 'Manage Coupons',
            'settings.manage' => 'Manage Store Settings',
            'audit.view' => 'View Audit Logs',
        ];
    }

    /** @return list<string> */
    public static function permissionKeys(): array
    {
        return array_keys(self::permissionLabels());
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
            'wishlist_product_ids' => 'array',
            'permissions' => 'array',
            'is_active' => 'boolean',
        ];
    }
}
