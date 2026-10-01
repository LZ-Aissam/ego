<?php

use App\Game\Calibration;
use App\Game\CalibrationPlan;
use App\Game\QuestScheduler;
use App\Models\Quest;
use App\Models\User;
use App\Services\ProgramBuilder;
use Database\Seeders\ExerciseSeeder;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->seed(ExerciseSeeder::class);
    $this->travelTo(Carbon::parse('2026-10-05 10:00'));          // lundi
    $this->user = User::factory()->withMorphology()->create();
    app(ProgramBuilder::class)->build($this->user, ['level' => 'beginner', 'goal' => 'hypertrophy', 'weekdays' => [1, 3, 5], 'equipment' => 'gym']);
});

it('assigne une quête de calibrage tant que le joueur n\'est pas calibré', function () {
    app(QuestScheduler::class)->sync($this->user, now());

    $quest = Quest::sole();
    expect($quest->type)->toBe('calibration')->and($quest->title)->toBe('Calibrage')
        ->and(array_column($quest->objectives, 'sets'))->toBe([3, 3, 3]);

    $this->actingAs($this->user)->get('/')->assertSee('QUÊTE DU JOUR')->assertSee(route('calibration.quest', $quest));
    $this->get(route('workout.quest', $quest))->assertRedirect(route('calibration.quest', $quest));
});

it('accomplit la quête par le calibrage, puis reprend au premier jour du programme', function () {
    app(QuestScheduler::class)->sync($this->user, now());
    $plan = app(CalibrationPlan::class)->plan($this->user);
    $results = array_map(fn ($m) => ['exercise_id' => $m['exercise_id'], 'warmups' => $m['warmups'], 'test' => [$m['isBodyweight'] ? 0 : 40, 8]], $plan);

    $out = app(Calibration::class)->complete($this->user, $results, now()->toIso8601String());

    expect(Quest::where('type', 'calibration')->value('status'))->toBe('done')
        ->and(collect($out['notifications'])->pluck('title'))->toContain('QUÊTE ACCOMPLIE')
        ->and($this->user->fresh()->streak_days)->toBe(1);

    app(QuestScheduler::class)->sync($this->user, Carbon::parse('2026-10-07'));
    expect(Quest::where('date', '2026-10-07')->value('type'))->toBe('training')
        ->and(Quest::where('date', '2026-10-07')->value('title'))->toBe('Full body A');
});

it('punit un calibrage raté comme une quête d\'entraînement et le repropose', function () {
    app(QuestScheduler::class)->sync($this->user, now());

    $notes = app(QuestScheduler::class)->sync($this->user, Carbon::parse('2026-10-07'));

    expect(collect($notes)->pluck('title'))->toContain('QUÊTE ÉCHOUÉE')
        ->and(Quest::where('date', '2026-10-06')->where('type', 'penalty')->exists())->toBeTrue()
        ->and(Quest::where('date', '2026-10-07')->value('type'))->toBe('calibration');
});
