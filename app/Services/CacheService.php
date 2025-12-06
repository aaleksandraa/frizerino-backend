<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

class CacheService
{
    /**
     * Cache TTL in seconds (1 hour default).
     */
    protected int $ttl = 3600;

    /**
     * Remember a value in cache.
     */
    public function remember(string $key, callable $callback, ?int $ttl = null): mixed
    {
        return Cache::remember($key, $ttl ?? $this->ttl, $callback);
    }

    /**
     * Remember a value in cache forever.
     */
    public function rememberForever(string $key, callable $callback): mixed
    {
        return Cache::rememberForever($key, $callback);
    }

    /**
     * Get a value from cache.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return Cache::get($key, $default);
    }

    /**
     * Put a value in cache.
     */
    public function put(string $key, mixed $value, ?int $ttl = null): bool
    {
        return Cache::put($key, $value, $ttl ?? $this->ttl);
    }

    /**
     * Remove a value from cache.
     */
    public function forget(string $key): bool
    {
        return Cache::forget($key);
    }

    /**
     * Clear cache by pattern.
     */
    public function forgetByPattern(string $pattern): void
    {
        // For file/database cache, we need to track keys manually
        // For Redis, we could use KEYS command
        Cache::flush(); // Simplified - in production, use tagged cache
    }

    /**
     * Generate cache key for salons list.
     */
    public function salonListKey(array $filters = []): string
    {
        return 'salons:list:' . md5(json_encode($filters));
    }

    /**
     * Generate cache key for single salon.
     */
    public function salonKey(int $salonId): string
    {
        return "salons:{$salonId}";
    }

    /**
     * Generate cache key for salon services.
     */
    public function salonServicesKey(int $salonId): string
    {
        return "salons:{$salonId}:services";
    }

    /**
     * Generate cache key for salon staff.
     */
    public function salonStaffKey(int $salonId): string
    {
        return "salons:{$salonId}:staff";
    }

    /**
     * Generate cache key for salon reviews.
     */
    public function salonReviewsKey(int $salonId): string
    {
        return "salons:{$salonId}:reviews";
    }

    /**
     * Invalidate all salon-related cache.
     */
    public function invalidateSalonCache(int $salonId): void
    {
        $this->forget($this->salonKey($salonId));
        $this->forget($this->salonServicesKey($salonId));
        $this->forget($this->salonStaffKey($salonId));
        $this->forget($this->salonReviewsKey($salonId));
    }
}
