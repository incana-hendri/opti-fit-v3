<?php

namespace App\Models;

use App\Enums\Role;
use App\Notifications\ResetPassword;
use App\Notifications\VerifyEmail;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasName;
use Filament\Panel;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;

/**
 * @property Role $role
 * @property Carbon|null $date_of_birth
 * @property Carbon|null $deactivated_at
 */
#[Fillable(['first_name', 'last_name', 'email', 'phone', 'date_of_birth', 'password', 'marketing_opt_in'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser, HasName, MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

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
            'role' => Role::class,
            'date_of_birth' => 'date',
            'terms_accepted_at' => 'datetime',
            'marketing_opt_in' => 'boolean',
            'deactivated_at' => 'datetime',
            'deletion_requested_at' => 'datetime',
        ];
    }

    /**
     * @return HasOne<Household, $this>
     */
    public function household(): HasOne
    {
        return $this->hasOne(Household::class);
    }

    public function isAdmin(): bool
    {
        return $this->role === Role::Admin;
    }

    public function isDeactivated(): bool
    {
        return $this->deactivated_at !== null;
    }

    public function fullName(): string
    {
        return trim($this->first_name.' '.$this->last_name);
    }

    // BR-1.7
    public function canAccessPanel(Panel $panel): bool
    {
        return $this->isAdmin() && ! $this->isDeactivated();
    }

    public function getFilamentName(): string
    {
        return $this->fullName();
    }

    // Email C1, queued (docs/06).
    public function sendEmailVerificationNotification(): void
    {
        $this->notify(new VerifyEmail);
    }

    /**
     * Email C2, queued (docs/06).
     *
     * @param  string  $token
     */
    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new ResetPassword($token));
    }
}
