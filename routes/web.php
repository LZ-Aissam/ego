<?php

use App\Http\Controllers\LogoutController;
use App\Http\Controllers\WorkoutController;
use App\Http\Controllers\WorkoutSyncController;
use App\Livewire\Auth\Login;
use App\Livewire\Auth\Register;
use App\Livewire\History;
use App\Livewire\Home;
use App\Livewire\Progress;
use App\Livewire\Records;
use App\Livewire\WorkoutShow;
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
    Route::get('/workout', [WorkoutController::class, 'create'])->name('workout');
    Route::get('/history', History::class)->name('history');
    Route::get('/history/{workout}', WorkoutShow::class)->name('history.show');
    Route::get('/records', Records::class)->name('records');
    Route::get('/progress/{exercise}', Progress::class)->name('progress');
    Route::view('/profile', 'pages.soon', ['title' => 'Profil', 'message' => 'Bientôt.'])->name('profile');
    Route::post('/workouts/sync', WorkoutSyncController::class)->name('workouts.sync');
    Route::post('/logout', LogoutController::class)->name('logout');
});
