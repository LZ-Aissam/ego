<?php

use App\Livewire\Crew\MemberPage;
use App\Models\Program;
use App\Models\User;
use App\Services\ProgramBuilder;
use Database\Seeders\ExerciseSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(ExerciseSeeder::class);
    $this->me = User::factory()->withMorphology(weightKg: 80.0)->create(['first_name' => 'Aïssam']);
    $this->friend = User::factory()->withMorphology(sex: 'female', weightKg: 60.0)->create(['first_name' => 'Renée']);
    $this->source = app(ProgramBuilder::class)->build($this->friend, ['level' => 'intermediate', 'goal' => 'strength', 'weekdays' => [1, 2, 4, 5], 'equipment' => 'gym']);
    $this->actingAs($this->me);
});

it('copie fidèlement le programme actif d\'un ami et l\'active', function () {
    Livewire::test(MemberPage::class, ['member' => $this->friend])
        ->assertSee('Copier ce programme')
        ->call('startCopy')->set('weekdays', [1, 3, 5, 6])->call('copyProgram')
        ->assertRedirect(route('program'));

    $copy = $this->me->fresh()->activeProgram()->with('days.exercises')->first();
    $shape = fn (Program $p) => $p->days->map(fn ($d) => [$d->name, $d->type, $d->exercises->map(fn ($e) => [$e->exercise_id, $e->slot, $e->sets, $e->reps_min, $e->reps_max, $e->rest_seconds])->all()])->all();

    expect($copy->id)->not->toBe($this->source->id)
        ->and($copy->name)->toBe('Programme de Renée · 4 j')
        ->and($shape($copy))->toBe($shape($this->source->fresh('days.exercises')))
        ->and($this->me->fresh()->training_weekdays)->toBe([1, 3, 5, 6])
        ->and($this->me->fresh()->level)->toBe('beginner');
});

it('exige autant de jours que le programme copié', function () {
    Livewire::test(MemberPage::class, ['member' => $this->friend])
        ->call('startCopy')->set('weekdays', [1, 3, 5])->call('copyProgram')
        ->assertHasErrors('weekdays')->assertSee('Choisis 4 jours.');

    expect($this->me->fresh()->active_program_id)->toBeNull();
});

it('prévient quand le programme demande plus de matériel', function () {
    $this->me->forceFill(['equipment' => 'dumbbells'])->save();

    Livewire::test(MemberPage::class, ['member' => $this->friend])->call('startCopy')
        ->assertSee('Ce programme demande : Salle complète. Tu as indiqué : Haltères seuls.');
});

it('recalcule les charges pour soi au lieu de copier celles de l\'ami', function () {
    $this->friend->forceFill(['calibrated_at' => now()])->save();
    app(\App\Game\LoadPrescriber::class)->prescribeProgram($this->friend->fresh());
    $this->me->forceFill(['calibrated_at' => now(), 'power_factor' => 1.0])->save();
    $line = $this->source->days->first()->exercises->first();

    Livewire::test(MemberPage::class, ['member' => $this->friend])->call('startCopy')->set('weekdays', [1, 2, 4, 5])->call('copyProgram');

    $mine = $this->me->workingLoads()->where('exercise_id', $line->exercise_id)->value('target_kg');
    $theirs = $this->friend->workingLoads()->where('exercise_id', $line->exercise_id)->value('target_kg');
    expect($mine)->not->toBeNull()->and($mine)->not->toBe($theirs);
});

it('ne propose pas la copie de son propre programme ni sans programme', function () {
    app(ProgramBuilder::class)->build($this->me, ['level' => 'beginner', 'goal' => 'fitness', 'weekdays' => [1, 3], 'equipment' => 'gym']);
    Livewire::test(MemberPage::class, ['member' => $this->me])->assertDontSee('Copier ce programme')->call('copyProgram')->assertForbidden();

    $empty = User::factory()->create();
    Livewire::test(MemberPage::class, ['member' => $empty])->assertDontSee('Copier ce programme')->call('copyProgram')->assertForbidden();
});
