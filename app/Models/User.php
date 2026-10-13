<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * The owner. This is a single-person application: there is one user, created by the installer.
 */
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'recovery_code',
        'remember_token',
    ];

    public function initials(): string
    {
        $words = preg_split('/\s+/', trim($this->name)) ?: [];

        return Str::upper(implode('', array_map(
            static fn (string $word): string => Str::substr($word, 0, 1),
            array_slice($words, 0, 2),
        ))) ?: '?';
    }

    /**
     * Issue a fresh recovery code, store its hash, and return the plain code to show ONCE.
     *
     * Grouped as XXXX-XXXX-XXXX-XXXX from an alphabet without 0/O/1/I so it can be copied off
     * paper without guessing. Any previous code stops working. Does not save.
     */
    public function issueRecoveryCode(): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $raw = '';

        for ($i = 0; $i < 16; $i++) {
            $raw .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }

        $code = implode('-', str_split($raw, 4));
        $this->recovery_code = Hash::make($code);

        return $code;
    }

    public function recoveryCodeMatches(string $code): bool
    {
        $normalised = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $code) ?? '');

        if ($this->recovery_code === null || strlen($normalised) !== 16) {
            return false;
        }

        return Hash::check(implode('-', str_split($normalised, 4)), $this->recovery_code);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
}
