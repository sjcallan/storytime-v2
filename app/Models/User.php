<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;
use Laravel\Fortify\TwoFactorAuthenticatable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, HasUlids, Notifiable, TwoFactorAuthenticatable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'profile_photo_path',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'remember_token',
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var list<string>
     */
    protected $appends = [
        'avatar',
    ];

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
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    /**
     * Get the URL to the user's profile photo.
     */
    protected function avatar(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->profile_photo_path
                ? Storage::disk('public')->url($this->profile_photo_path)
                : null,
        );
    }

    /**
     * Delete the user's profile photo.
     */
    public function deleteProfilePhoto(): void
    {
        if ($this->profile_photo_path) {
            Storage::disk('public')->delete($this->profile_photo_path);
            $this->forceFill(['profile_photo_path' => null])->save();
        }
    }

    /**
     * Get all profiles for the user.
     */
    public function profiles(): HasMany
    {
        return $this->hasMany(Profile::class);
    }

    /**
     * Get the user's default profile.
     */
    public function defaultProfile(): ?Profile
    {
        return $this->profiles()->where('is_default', true)->first();
    }

    /**
     * Create a default profile for the user.
     */
    public function createDefaultProfile(): Profile
    {
        return $this->profiles()->create([
            'name' => $this->name,
            'age_group' => '18',
            'is_default' => true,
        ]);
    }
}
