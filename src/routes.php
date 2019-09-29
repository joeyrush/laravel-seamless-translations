<?php

use JoeyRush\SeamlessTranslations\Controllers\LocalesController;

Route::middleware('web')->get('/locale/{locale}', LocalesController::class . '@switch');
