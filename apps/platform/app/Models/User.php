<?php

namespace App\Models;

use App\Domain\Tenancy\Models\Business;
use App\Enums\AccountStatus;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'email', 'phone', 'password', 'account_status', 'terms_accepted_at', 'privacy_accepted_at'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

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
            'account_status' => AccountStatus::class,
            'terms_accepted_at' => 'datetime',
            'privacy_accepted_at' => 'datetime',
        ];
    }

    public function hasActiveAccount(): bool
    {
        return $this->account_status === AccountStatus::Active;
    }

    public function hasDisabledAccount(): bool
    {
        return in_array($this->account_status, [AccountStatus::Suspended, AccountStatus::Disabled], true);
    }

    public function ownedBusinesses(): BelongsToMany
    {
        return $this->belongsToMany(Business::class, 'business_owners')
            ->withPivot('role')
            ->withTimestamps();
    }
}
