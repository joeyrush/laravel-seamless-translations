<?php

namespace JoeyRush\SeamlessTranslations\Middleware;

use Closure;
use Illuminate\Support\Facades\App;
use Illuminate\View\Factory;

class CheckLocale
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        $locale = session('locale');

        if (!empty($locale)) {
            App::setLocale($locale);
        }

        return $next($request);
    }
}
