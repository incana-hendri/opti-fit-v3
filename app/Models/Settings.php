<?php

namespace App\Models;

use Database\Factories\SettingsFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * The studio settings: a single row (BR-9.1).
 */
class Settings extends Model
{
    /** @use HasFactory<SettingsFactory> */
    use HasFactory;

    private const CACHE_KEY = 'settings.current';

    public const DEFAULTS = [
        'studio_name' => 'OptiFit',
        'contact_email' => 'info@opti-fit.co.za',
        'contact_phone' => null,
        'admin_notification_emails' => 'info@opti-fit.co.za',
        'default_cutoff_hours' => 8,
        'booking_horizon_days' => 14,
        'terms_version' => '2026-11',
        'payment_instructions' => null,
    ];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'default_cutoff_hours' => 'integer',
            'booking_horizon_days' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::saved(fn () => Cache::forget(self::CACHE_KEY));
        static::deleted(fn () => Cache::forget(self::CACHE_KEY));
    }

    public static function current(): self
    {
        // Cache plain attributes: the cache store refuses to unserialize objects (config/cache.php).
        $attributes = Cache::rememberForever(self::CACHE_KEY, fn () => static::query()->firstOrFail()->getAttributes());

        return (new self)->newFromBuilder($attributes);
    }

    /**
     * @return list<string>
     */
    public function adminNotificationEmails(): array
    {
        return array_values(array_filter(array_map('trim', explode(',', (string) $this->admin_notification_emails))));
    }
}
