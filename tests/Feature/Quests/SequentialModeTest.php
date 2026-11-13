<?php

use App\Models\Program;
use App\Models\Quest;
use App\Models\User;
use App\Game\QuestScheduler;
use App\Services\ProgramBuilder;
use Database\Seeders\ExerciseSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(ExerciseSeeder::class);
    $this->user = User::factory()->create();
    $this->scheduler = app(QuestScheduler::class);
});

/** Programme Force pure, haut du corps, 5 jours, en mode séquentiel ; calibré. */
function sequentialProgram(User $user, string $createdAt = '2026-10-05'): Program
{
    $program = app(ProgramBuilder::class)->build($user, [
        'level' => 'advanced', 'goal' => 'pure', 'upper_only' => true,
        'weekdays' => [1, 2, 3, 4, 5], 'equipment' => 'gym',
    ]);
    $program->forceFill(['created_at' => Carbon::parse($createdAt.' 09:00')])->save();
    $program->update(['params' => [...$program->params, 'sequencing' => 'sequential']]);
    $user->forceFill(['calibrated_at' => Carbon::parse($createdAt.' 09:30')])->save();

    return $program->fresh();
}

function d(string $date): Carbon
{
    return Carbon::parse($date);
}

it('reconnaît un programme séquentiel', function () {
    $program = sequentialProgram($this->user);
    expect($program->isSequential())->toBeTrue();

    $program->update(['params' => [...$program->params, 'sequencing' => 'calendar']]);
    expect($program->fresh()->isSequential())->toBeFalse();
});

it('ouvre une seule séance active, la première du programme, même un jour non travaillé du calendrier', function () {
    $program = sequentialProgram($this->user);

    $this->scheduler->sync($this->user, d('2026-10-10'));                         // samedi, hors des jours [1..5]

    $quest = Quest::where('type', 'training')->sole();
    expect($quest->status)->toBe('pending')
        ->and($quest->date->toDateString())->toBe('2026-10-10')
        ->and($quest->program_day_id)->toBe($program->days->first()->id)
        ->and(Quest::where('type', 'rest')->count())->toBe(0);
});

it('ne fait pas échouer la séance quand des jours passent, elle suit le jour courant', function () {
    $program = sequentialProgram($this->user);
    $this->scheduler->sync($this->user, d('2026-10-05'));

    $notes = $this->scheduler->sync($this->user, d('2026-10-08'));

    expect($notes)->toBe([])
        ->and(Quest::where('status', 'failed')->count())->toBe(0)
        ->and(Quest::where('type', 'rest')->count())->toBe(0)
        ->and(Quest::where('type', 'training')->count())->toBe(1);

    $quest = Quest::where('type', 'training')->sole();
    expect($quest->status)->toBe('pending')
        ->and($quest->date->toDateString())->toBe('2026-10-08')
        ->and($quest->program_day_id)->toBe($program->days->first()->id);
});

it('passe à la séance suivante dans l\'ordre, pas à celle du jour de la semaine', function () {
    $program = sequentialProgram($this->user);
    $this->scheduler->sync($this->user, d('2026-10-05'));
    Quest::where('type', 'training')->update(['status' => 'done', 'completed_at' => now()]);

    $this->scheduler->sync($this->user, d('2026-10-08'));                         // jeudi = J4 en calendaire, J2 en rotation

    $pending = Quest::where('type', 'training')->where('status', 'pending')->sole();
    expect($pending->date->toDateString())->toBe('2026-10-08')
        ->and($pending->program_day_id)->toBe($program->days[1]->id);
});

it('ne crée pas de deuxième séance le jour où une a déjà été faite', function () {
    sequentialProgram($this->user);
    $this->scheduler->sync($this->user, d('2026-10-05'));
    Quest::where('type', 'training')->update(['status' => 'done', 'completed_at' => now()]);

    $this->scheduler->sync($this->user, d('2026-10-05'));

    expect(Quest::where('date', '2026-10-05')->where('type', 'training')->count())->toBe(1)
        ->and(Quest::where('type', 'training')->where('status', 'pending')->count())->toBe(0);
});

/** Crée $count séances « faites » sur des dates consécutives, dans l'ordre du programme. */
function doneSessions(User $user, Program $program, int $count, string $from = '2026-10-05'): void
{
    $days = $program->days;
    foreach (range(0, $count - 1) as $i) {
        $user->quests()->create([
            'date' => d($from)->addDays($i)->toDateString(),
            'type' => 'training', 'program_day_id' => $days[$i % $days->count()]->id,
            'title' => $days[$i % $days->count()]->name, 'objectives' => [], 'status' => 'done', 'completed_at' => now(),
        ]);
    }
}

it('fait avancer le cycle au rythme des séances faites (5 faites = semaine 2)', function () {
    $program = sequentialProgram($this->user, '2026-10-05');
    doneSessions($this->user, $program, 5);                                       // 10-05 → 10-09

    // Le 11 : 5 séances faites avant → semaine 2 (en calendaire, le 11 serait encore semaine 1).
    expect(\App\Game\Cycle::week($this->user->fresh(), d('2026-10-11')))->toBe(2);
});

it('passe en semaine allégée après 15 séances faites', function () {
    $program = sequentialProgram($this->user, '2026-10-05');
    doneSessions($this->user, $program, 15);

    expect(\App\Game\Cycle::isDeload($this->user->fresh(), d('2026-10-25')))->toBeTrue();
});

it('ne change pas de semaine de cycle quand seul le calendrier défile', function () {
    $program = sequentialProgram($this->user, '2026-10-05');
    doneSessions($this->user, $program, 2);

    // Trois semaines calendaires plus tard, mais seulement 2 séances faites : encore semaine 1.
    expect(\App\Game\Cycle::week($this->user->fresh(), d('2026-10-26')))->toBe(1);
});

it('compte la série en séances faites, les jours sans séance restant neutres', function () {
    sequentialProgram($this->user);
    $this->scheduler->sync($this->user, d('2026-10-05'));
    Quest::where('type', 'training')->where('status', 'pending')->update(['status' => 'done', 'completed_at' => now()]);

    $this->scheduler->sync($this->user, d('2026-10-08'));                         // 2 jours sans séance
    Quest::where('type', 'training')->where('status', 'pending')->update(['status' => 'done', 'completed_at' => now()]);

    $this->scheduler->sync($this->user, d('2026-10-09'));

    expect($this->user->fresh()->streak_days)->toBe(2);
});

it('une séance envoyée après des jours d\'absence ramène la séance active au jour de la séance', function () {
    sequentialProgram($this->user);
    $this->scheduler->sync($this->user, d('2026-10-05'));

    $workout = $this->user->workouts()->create([
        'client_id' => (string) Str::uuid(), 'started_at' => '2026-10-08 18:00:00',
        'finished_at' => '2026-10-08 19:00:00', 'bodyweight_kg' => 70,
    ]);
    $this->scheduler->onWorkout($this->user, $workout);

    $pending = Quest::where('type', 'training')->where('status', 'pending')->sole();
    expect($pending->date->toDateString())->toBe('2026-10-08');
});

it('le profil bascule le programme en mode séquentiel puis calendaire', function () {
    $program = sequentialProgram($this->user);
    $program->update(['params' => [...$program->params, 'sequencing' => 'calendar']]);
    $this->actingAs($this->user);

    Livewire::test(\App\Livewire\Profile::class)->call('setSequencing', 'sequential');
    expect($program->fresh()->isSequential())->toBeTrue();

    Livewire::test(\App\Livewire\Profile::class)->call('setSequencing', 'calendar');
    expect($program->fresh()->isSequential())->toBeFalse();
});

it('le profil montre le choix du mode d\'enchaînement', function () {
    sequentialProgram($this->user);
    $this->actingAs($this->user);

    $this->get('/profile')->assertSee('ENCHAÎNEMENT')->assertSee('Séquentiel');
});

it('l\'accueil crée et montre la séance active en mode séquentiel (via le middleware)', function () {
    $program = sequentialProgram($this->user);
    $this->actingAs($this->user);

    $this->get('/')->assertOk()->assertSee($program->days->first()->name);

    expect(Quest::where('type', 'training')->where('status', 'pending')->count())->toBe(1);
});

it('en passant en séquentiel, repart à la rotation après la dernière séance faite, en ignorant une quête calendaire en attente', function () {
    $program = sequentialProgram($this->user, '2026-10-05');
    $program->update(['params' => [...$program->params, 'sequencing' => 'calendar']]);
    $days = $program->days;
    $this->user->quests()->create(['date' => '2026-10-06', 'type' => 'training', 'program_day_id' => $days[1]->id, 'title' => $days[1]->name, 'objectives' => [], 'status' => 'done', 'completed_at' => now()]);
    $this->user->quests()->create(['date' => '2026-10-07', 'type' => 'training', 'program_day_id' => $days[3]->id, 'title' => $days[3]->name, 'objectives' => [], 'status' => 'pending']);
    $this->actingAs($this->user);

    Livewire::test(\App\Livewire\Profile::class)->call('setSequencing', 'sequential');
    $this->scheduler->sync($this->user->fresh(), d('2026-10-10'));

    $pending = Quest::where('type', 'training')->where('status', 'pending')->sole();
    expect($pending->program_day_id)->toBe($days[2]->id);                         // J3, la rotation après J2
});
