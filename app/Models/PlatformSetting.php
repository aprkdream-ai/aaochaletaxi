<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;

class PlatformSetting extends Model
{
    protected $fillable = ['key', 'value', 'is_secret'];

    protected function casts(): array
    {
        return ['is_secret' => 'boolean'];
    }

    public static function getValue(string $key, mixed $default = null): mixed
    {
        $all = Cache::remember('platform_settings', 600, fn () => static::query()->get(['key', 'value', 'is_secret'])
            ->mapWithKeys(fn ($s) => [$s->key => ['v' => $s->value, 's' => $s->is_secret]])->all());

        if (! array_key_exists($key, $all) || $all[$key]['v'] === null) {
            return $default;
        }

        return $all[$key]['s'] ? Crypt::decryptString($all[$key]['v']) : $all[$key]['v'];
    }

    public static function setValue(string $key, ?string $value, bool $secret = false): void
    {
        static::query()->updateOrCreate(['key' => $key], [
            'value' => $value !== null && $secret ? Crypt::encryptString($value) : $value,
            'is_secret' => $secret,
        ]);
        Cache::forget('platform_settings');
    }
}
