<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * IIUM kulliyyahs a student can choose on the profile page (code => name).
     */
    public const KULLIYYAHS = [
        'KICT' => 'ICT',
        'KOE' => 'Engineering',
        'KENMS' => 'Economics',
        'IRKHS' => 'Human Sciences',
        'AIKOL' => 'Laws',
        'KAED' => 'Architecture',
        'KOS' => 'Science',
        'KOM' => 'Medicine',
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'kulliyyah',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
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
            'is_admin' => 'boolean',
        ];
    }

    // Add this inside your User class
public function simulationAttempts()
{
    return $this->hasMany(SimulationAttempt::class);
}
}
