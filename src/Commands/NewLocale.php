<?php

namespace JoeyRush\SeamlessTranslations\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class NewLocale extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'locale:new {locale}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Prepare the migration file for a new locale';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $locale = $this->argument('locale');
        if ($this->localeExists($locale)) {
            $this->error("Locale $locale already exists - detected translations_$locale table");
            return;
        }

        if ($this->isDefaultLocale($locale)) {
            $this->error("Cannot create $locale because this is your applications default locale.");
            return;
        }

        $this->info("Creating migrations for the $locale locale");
        $timestamp = date('Y_m_d_His');
        $stub = File::get(__DIR__ . '/../stubs/new_translations_table_migration.stub');
        $result = File::put(
            database_path("migrations/{$timestamp}_create_{$locale}_locale.php"),
            str_replace(['{locale}', '{localeUpper}'], [$locale, ucfirst($locale)], $stub)
        );

        if ($result) {
            $this->info('Migration file generated successfully. Now check it and run artisan migrate!');
        } else {
            $this->error('Something went wrong with the migration generation.');
        }
    }

    private function localeExists($locale)
    {
        $tables = array_map('reset', DB::select('SHOW TABLES'));

        return in_array("translations_$locale", $tables);
    }

    private function isDefaultLocale($locale)
    {
        return config('app.locale') == $locale;
    }
}
