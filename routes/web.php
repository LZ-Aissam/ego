<?php

use App\Http\Controllers\LogoutController;
use App\Http\Controllers\WorkoutSyncController;
use App\Livewire\Auth\Login;
use App\Livewire\Auth\Register;
use App\Livewire\Home;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/register', Register::class)->name('register');
    Route::get('/login', Login::class)->name('login');
});

Route::middleware('auth')->group(function () {
    Route::get('/', Home::class)->name('home');
    Route::view('/program', 'pages.soon', [
        'title' => 'Programme',
        'message' => 'Le générateur de programmes arrive à la prochaine étape.',
    ])->name('program');
    Route::view('/community', 'pages.soon', [
        'title' => 'Communauté',
        'message' => 'Records des amis, classement, défis et groupes arrivent bientôt.',
    ])->name('community');
    // Provisoires, remplacées aux tâches 10 à 12
    Route::view('/workout', 'pages.soon', ['title' => 'Séance', 'message' => 'Bientôt.'])->name('workout');
    Route::view('/history', 'pages.soon', ['title' => 'Historique', 'message' => 'Bientôt.'])->name('history');
    Route::view('/records', 'pages.soon', ['title' => 'Records', 'message' => 'Bientôt.'])->name('records');
    Route::view('/profile', 'pages.soon', ['title' => 'Profil', 'message' => 'Bientôt.'])->name('profile');
    Route::post('/workouts/sync', WorkoutSyncController::class)->name('workouts.sync');
    Route::post('/logout', LogoutController::class)->name('logout');
});
