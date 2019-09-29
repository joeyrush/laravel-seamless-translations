# Laravel Seamless Translations
Add scalable i18n (multi-lingual) support to your laravel database seamlessly, meaning you don't have to:
1. Modify any of your existing tables
2. Change the way you store, retrieve and modify your data
3. Litter your tables with un-indexable TEXT columns for holding chunky and difficult-to-work-with data

## Quick overview
 This packages works by creating a separate table for each locale you intend to provide support for. If you were supporting english, spanish and french then you would have the following tables:
 - `translations_fr`
 - `translations_es`
 
 > Notice how there is no dedicated table for english. This is assuming en is the default locale as the english data will already be stored as the default data in your existing tables.
 
 Using eloquent lifecycle events, we can **seamlessly** swap out the attributes on your model depending on the [current locale](https://laravel.com/docs/5.7/localization#configuring-the-locale) whilst retrieving data, and likewise we can store data in the appropriate translation table when updating or creating a new record.
 
 ### But does it scale?
 Each table will hold translations for every model in your application. The benefits of this approach in terms of scalability are:
 1. We aren't querying a single enormous table containing every single translation for every single language
 2. Nor are we having to filter on JSON/TEXT fields for every single query within our application
 3. Each translation table can be (and is) cached
 4. You only need to load a single set of translations at once
 5. Any field can be translated at any point without having to modify or add new columns
 6. New languages only require one new table which has no risk of causing a breaking change 
 
 ## Installation
 Install with composer:
 ```
composer require joeyrush/laravel-seamless-translations
```

## Setup
1. Attach the `Translatable` trait to your base model. We advise a base model rather than individual models purely for convenience. No model will be affected until you specify which fields you want to be translatable.

```php
class Model extends Eloquent
{
    use \JoeyRush\SeamlessTranslations\Traits\Translatable;
}
```

2. Specify which fields you would like to be translated on a per-model basis:

```php
class Post extends Model
{
    // Assuming the Translatable trait has been applied to the parent model.
    // If not, add "use \JoeyRush\SeamlessTranslations\Traits\Translatable;" to this model.
    
    public $translatable = ['title', 'body'];
}
```

3. Run `php artisan vendor:publish` and select "JoeyRush\SeamlessTranslations\ServiceProvider" to publish the locales table migration. This is a list of locales that are enabled in your application.

4. Setup your first non-default locale by running `php artisan locale:new es` where "es" is the locale code. This will publish another migration file for creating the `translations_es` table and adding `es` to the list of locales.

5. Run the migrations

There is no additional API for retrieving, creating, updating or deleting translations because this all happens automatically when you work with eloquent based on the [current locale](https://laravel.com/docs/5.7/localization#configuring-the-locale).

## Setting the current locale
Laravel provides a way to change the locale at runtime using `App::setLocale('es')` but this won't be persisted between requests. We provide an endpoint for persisting the locale via sessions: `/locale/{locale}`.

> E.g. /locale/es will put `es` (spanish) into the session and the `CheckLocale` middleware will use this value with `App::setLocale()` on every web request.

## Fetch specific language translations
If you need to pull out translations for a locale other than the current locale, you can use a `locale()` query scope:

```php
// Get german translations for posts regardless of the current locale.
$posts = Post::locale('de')->get();

// For eager-loading an alternative locale, you will also need to attach the scope on the related model.
$posts = Post::locale('de')->with(['tags' => function ($query) {
    $query->locale('de');
}]);
```

