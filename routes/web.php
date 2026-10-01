<?php

use App\Http\Controllers\CrewJoinController;
use App\Livewire\Crew\ChallengePage;
use App\Livewire\Crew\CrewPage;
use App\Livewire\Crew\GroupPage;
use App\Livewire\Crew\MemberPage;
use App\Http\Controllers\LogoutController;
use App\Http\Controllers\WorkoutController;
use App\Http\Controllers\WorkoutSyncController;
use App\Http\Middleware\SyncQuests;
use App\Livewire\Auth\Login;
use App\Livewire\CalibrationPage;
use App\Livewire\Auth\Register;
use App\Livewire\History;
use App\Livewire\Home;
use App\Livewire\Profile;
use App\Livewire\ProgramPage;
use App\Livewire\Progress;
use App\Livewire\Records;
use App\Livewire\WorkoutShow;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/register', Register::class)->name('register');
    Route::get('/login', Login::class)->name('login');
});

Route::middleware(['auth', SyncQuests::class])->group(function () {
    Route::get('/', Home::class)->name('home');
    Route::get('/program', ProgramPage::class)->name('program');
    Route::get('/calibration', CalibrationPage::class)->name('calibration');
    Route::get('/calibration/quest/{quest}', CalibrationPage::class)->name('calibration.quest');
    Route::redirect('/community', '/crew');
    Route::get('/crew', CrewPage::class)->name('community');
    Route::get('/crew/membres/{member}', MemberPage::class)->name('crew.member');
    Route::get('/crew/groupes/{crew}', GroupPage::class)->name('crew.group');
    Route::get('/crew/defis/{challenge}', ChallengePage::class)->name('crew.challenge');
    Route::get('/crew/rejoindre/{code}', CrewJoinController::class)->name('crew.join');
    Route::get('/workout', [WorkoutController::class, 'create'])->name('workout');
    Route::get('/workout/quest/{quest}', [WorkoutController::class, 'quest'])->name('workout.quest');
    Route::get('/history', History::class)->name('history');
    Route::get('/history/{workout}', WorkoutShow::class)->name('history.show');
    Route::get('/records', Records::class)->name('records');
    Route::get('/progress/{exercise}', Progress::class)->name('progress');
    Route::get('/profile', Profile::class)->name('profile');
    Route::post('/workouts/sync', WorkoutSyncController::class)->name('workouts.sync');
    Route::post('/logout', LogoutController::class)->name('logout');
});
