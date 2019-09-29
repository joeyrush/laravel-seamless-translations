<?php

namespace JoeyRush\SeamlessTranslations\Models;

use Illuminate\Database\Eloquent\Model;

class Locale extends Model
{
    protected $guarded = [];

    public static function getEnabled()
    {
        return static::all()->pluck('code')->toArray();
    }
}
