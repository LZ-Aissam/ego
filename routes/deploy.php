<?php

use App\Http\Controllers\DeployUpdateController;
use Illuminate\Support\Facades\Route;

// Hors du groupe « web » : sans session ni cookie, la page marche avant même que la base existe.
Route::get('/systeme/maj', [DeployUpdateController::class, 'show']);
Route::post('/systeme/maj', [DeployUpdateController::class, 'run']);
