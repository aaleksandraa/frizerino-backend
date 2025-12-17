<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserConsent extends Model
{
    use HasFactory;

    /**
     * Tipovi pristanka
     */
    const TYPE_PRIVACY_POLICY = 'privacy_policy';           // Pravila privatnosti
    const TYPE_CONTACT_COMMUNICATION = 'contact_communication'; // Pristanak za kontakt
    const TYPE_PUBLIC_DATA_DISPLAY = 'public_data_display';     // Javni prikaz podataka (za salone)
    const TYPE_MARKETING = 'marketing';                          // Marketing komunikacija

    /**
     * Trenutna verzija uslova
     */
    const CURRENT_VERSION = '1.0';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'user_id',
        'consent_type',
        'accepted',
        'version',
        'ip_address',
        'user_agent',
        'accepted_at',
        'revoked_at',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'accepted' => 'boolean',
        'accepted_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    /**
     * Get the user that owns the consent.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Kreiranje ili ažuriranje pristanka
     */
    public static function recordConsent(
        int $userId,
        string $consentType,
        bool $accepted,
        ?string $ipAddress = null,
        ?string $userAgent = null
    ): self {
        $consent = self::updateOrCreate(
            [
                'user_id' => $userId,
                'consent_type' => $consentType,
            ],
            [
                'accepted' => (bool) $accepted, // Explicitly cast to boolean for PostgreSQL
                'version' => self::CURRENT_VERSION,
                'ip_address' => $ipAddress,
                'user_agent' => $userAgent,
                'accepted_at' => $accepted ? now() : null,
                'revoked_at' => !$accepted ? now() : null,
            ]
        );

        return $consent;
    }

    /**
     * Provjeri da li korisnik ima aktivan pristanak
     */
    public static function hasActiveConsent(int $userId, string $consentType): bool
    {
        return self::where('user_id', $userId)
            ->where('consent_type', $consentType)
            ->where('accepted', true)
            ->whereNull('revoked_at')
            ->exists();
    }

    /**
     * Dohvati sve pristanke korisnika
     */
    public static function getUserConsents(int $userId): array
    {
        $consents = self::where('user_id', $userId)->get();

        $result = [];
        foreach ($consents as $consent) {
            $result[$consent->consent_type] = [
                'accepted' => $consent->accepted,
                'version' => $consent->version,
                'accepted_at' => $consent->accepted_at?->toISOString(),
            ];
        }

        return $result;
    }

    /**
     * Scope za aktivne pristanke
     */
    public function scopeActive($query)
    {
        return $query->where('accepted', true)->whereNull('revoked_at');
    }

    /**
     * Scope po tipu pristanka
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('consent_type', $type);
    }

    /**
     * Labele za tipove pristanaka
     */
    public static function getConsentLabels(): array
    {
        return [
            self::TYPE_PRIVACY_POLICY => 'Pravila privatnosti i uslovi korištenja',
            self::TYPE_CONTACT_COMMUNICATION => 'Pristanak za kontakt komunikaciju',
            self::TYPE_PUBLIC_DATA_DISPLAY => 'Javni prikaz podataka',
            self::TYPE_MARKETING => 'Marketing i promotivne poruke',
        ];
    }
}
