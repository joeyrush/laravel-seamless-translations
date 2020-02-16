<?php
namespace JoeyRush\SeamlessTranslations\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Container\Container;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

trait Translatable
{
    protected $withTranslations = true;

    private $localeForQuery = null;

    public static function bootTranslatable()
    {
        static::updating([static::class, 'modelUpdatingCallback']);
        static::created([static::class, 'modelUpdatingCallback']);
        static::deleting([static::class, 'modelDeletingCallback']);

        $model = static::make([]);
        $table = $model->table;
        $primaryKey = $model->primaryKey;
        Builder::macro('whereTranslation', function ($column, $operator, $query = null) use ($table, $primaryKey) {
            $locale = $this->model->getQueryLocale() ?? \App::getLocale();
            if ($locale == config('app.fallback_locale')) {
                return $this->where($column, $operator, $query);
            }

            if (count(func_get_args()) < 3) {
                $query = $operator;
                $operator = "=";
            }

            $subQuery = \DB::table("translations_$locale")
                ->select('translation')
                ->where('related_table', $table)
                ->where('related_field', "$column")
                ->whereColumn('related_id', "{$table}.{$primaryKey}")
                ->take(1);

            $this->whereRaw(
                "(" . $subQuery->toSql() . ") $operator ?",
                array_merge($subQuery->getBindings(), [$query])
            );

            return $this;
        });
    }

    public function shouldTranslateTo($locale)
    {
        if (! $this->withTranslations) {
            return false;
        }

        return $locale != config('app.fallback_locale')
            && !empty($this->translatable)
            && in_array("translations_$locale", $this->getTableList());
    }

    public static function modelDeletingCallback($model)
    {
        collect($model->getTableList())
            ->filter(function ($table) {
                return Str::startsWith($table, 'translations_');
            })
            ->each(function ($translationTable) use ($model) {
                // Delete the related translations silently. If the locale table doesn't exist or there are
                // no translations in this particular table, we don't want an exception to be thrown.
                rescue(function () use ($translationTable, $model) {
                    DB::table($translationTable)->where([
                        'related_table' => $model->table,
                        'related_id' => $model->{$model->primaryKey}
                    ])->delete();

                    Cache::forget($translationTable);
                });
            });

        return true;
    }

    public static function modelUpdatingCallback($model)
    {
        $locale = App::getLocale();

        // The $table property isn't available on newly created model instances
        $table = $model->newInstance()->table;

        if (!$model->shouldTranslateTo($locale)) {
            return true;
        }

        foreach ($model->translatable as $field) {
            if (!isset($model->$field)) {
                continue;
            }

            DB::table("translations_$locale")->updateOrInsert([
                'related_table' => $table,
                'related_field' => $field,
                'related_id' => $model->{$model->primaryKey}
            ], [
                'related_table' => $table,
                'related_field' => $field,
                'related_id' => $model->{$model->primaryKey},
                'translation' => $model->$field
            ]);
        }

        $model->withoutTranslations()->refresh();

        Cache::forget("translations_$locale");

        return true;
    }

    public function withoutTranslations()
    {
        $this->withTranslations = false;

        return $this;
    }

    public function scopeLocale($query, string $locale)
    {
        $this->localeForQuery = $locale;
    }

    public function getQueryLocale()
    {
        return $this->localeForQuery;
    }

    /**
     * Create a collection of models from plain arrays.
     * Overridden to replace fields with translations depending on the apps locale
     *
     * @param  array  $items
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function hydrate(array $items)
    {
        $locale = $this->localeForQuery ?? session('locale') ?? App::getLocale();

        if (!$this->shouldTranslateTo($locale)) {
            return parent::hydrate($items);
        }

        $allTranslations = Cache::rememberForever("translations_$locale", function () use ($locale) {
            return DB::table("translations_$locale")->get();
        });

        $instance = $this->newModelInstance();
        $modelTranslations = $allTranslations
            ->where('related_table', $instance->table)
            ->whereIn('related_id', array_column($items, $instance->primaryKey));

        if ($modelTranslations->count()) {
            $items = $this->injectTranslatedFields($items, $modelTranslations);
        }

        return parent::hydrate($items);
    }

    private function injectTranslatedFields($items, $modelTranslations)
    {
        foreach ($items as $key => $item) {
            $translatedFields = $modelTranslations->where('related_id', $item->id)
                    ->pluck('translation', 'related_field')
                    ->toArray();

            $items[$key] = (object) array_merge((array) $item, $translatedFields);
        }

        return $items;
    }

    private function getTableList()
    {
        $app = Container::getInstance();
        if (! $app->has('tables')) {
            $tables = Cache::rememberForever('schema.tables', function () {
                try {
                    $rawTables = DB::select('SHOW TABLES'); // mysql
                } catch (\Illuminate\Database\QueryException $e) {
                    $rawTables = DB::select('SELECT name FROM sqlite_master'); // sqlite
                }

                return array_map('reset', $rawTables);
            });

            $app->bind('tables', function () use ($tables) {
                return $tables;
            });
        }

        return $app->get('tables');
    }
}
