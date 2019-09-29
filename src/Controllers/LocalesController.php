<?php

namespace JoeyRush\SeamlessTranslations\Controllers;

use JoeyRush\SeamlessTranslations\Models\Locale;

class LocalesController
{
    public function switch($locale)
    {
        if (!in_array($locale, Locale::getEnabled())) {
            $locale = config('app.locale');
        }

        session(['locale' => $locale]);
        return back();
    }
}
