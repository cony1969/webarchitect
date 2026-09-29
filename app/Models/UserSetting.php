<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserSetting extends Model
{
    protected $fillable = ['user_id', 'name', 'value'];

    // Pomocná statická funkcia pre rýchly zápis
    public static function set(string $name, $value): void
    {
        if (auth()->check()) {
            self::updateOrCreate(
                ['user_id' => auth()->id(), 'name' => $name],
                ['value' => $value]
            );
        }
    }

    // Pomocná statická funkcia pre rýchle čítanie s fallbackom
    public static function get(string $name, $default = null)
    {
        if (auth()->check()) {
            $setting = self::where('user_id', auth()->id())->where('name', $name)->first();
            return $setting ? $setting->value : $default;
        }
        return $default;
    }
}
