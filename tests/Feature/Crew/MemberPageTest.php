<?php

use App\Crew\CrewService;
use App\Livewire\Crew\MemberPage;
use App\Models\Exercise;
use App\Models\User;
use App\Services\ProgramBuilder;
use Database\Seeders\ExerciseSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(ExerciseSeeder::class);
    $this->me = User::factory()->create(['first_name' => 'Aïssam']);
    $this->member = User::factory()->withMorphology(sex: 'female', heightCm: 187, weightKg: 83.4)->create(['first_name' => 'Renée', 'birth_date' => '1993-02-17']);
    $this->member->forceFill(['xp_total' => 400, 'streak_days' => 6])->save();
    $this->actingAs($this->me);
});

it('montre la carte de joueur, les records, le profil de force, le programme et les groupes en commun', function () {
    $bench = Exercise::firstWhere('slug', 'bench-press');
    $this->member->personalRecords()->create(['exercise_id' => $bench->id, 'max_weight_kg' => 50, 'max_weight_reps' => 5, 'best_e1rm_kg' => 58.3, 'achieved_at' => now()]);
    $this->member->exerciseMaxes()->create(['exercise_id' => $bench->id, 'e1rm_kg' => 57.5, 'source' => 'calibration', 'measured_at' => now()]);
    app(ProgramBuilder::class)->build($this->member, ['level' => 'beginner', 'goal' => 'hypertrophy', 'weekdays' => [1, 3, 5], 'equipment' => 'gym']);
    $crew = app(CrewService::class)->create($this->me, 'Les Ombres', 'violet');
    app(CrewService::class)->join($this->member, $crew->code);
    app(CrewService::class)->create($this->member, 'Privé de Renée', 'red');

    $this->get(route('crew.member', $this->member))->assertOk()
        ->assertSee('Renée')->assertSee('Niveau '.$this->member->player()['level'])->assertSee('Série 6 j')
        ->assertSee('Développé couché (barre)')->assertSee('58,3 kg')->assertSee('57,5 kg')
        ->assertSee('PROGRAMME ACTIF')->assertSee('Les Ombres')->assertDontSee('Privé de Renée');
});

it('ne montre jamais les données privées', function () {
    $this->member->update(['height_cm' => 187, 'weight_kg' => 83.4]);

    $this->get(route('crew.member', $this->member))->assertOk()
        ->assertDontSee('83,4')->assertDontSee('83.4')->assertDontSee('187 cm')->assertDontSee('"height_cm"', false)
        ->assertDontSee('1993-02-17')->assertDontSee('17/02/1993')->assertDontSee('17 février')->assertDontSee('IMC')
        ->assertDontSee(' ans ')->assertDontSee('Historique');
});

it('affiche des états vides pour un joueur sans programme, record ni titre', function () {
    $this->get(route('crew.member', $this->member))->assertOk()
        ->assertSee('Aucun record pour l\'instant.', false)->assertSee('Pas encore calibré.')->assertSee('Pas de programme actif.');
});

it('répond 404 pour un membre inexistant', function () {
    $this->get('/crew/membres/999999')->assertNotFound();
});

it('verrouille l\'identifiant du membre affiché', function () {
    Livewire::test(MemberPage::class, ['member' => $this->member])->set('memberId', $this->me->id);
})->throws(\Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException::class);

it('propose « Voir comme les autres » sur mon profil', function () {
    $this->get(route('profile'))->assertSee(route('crew.member', $this->me));
});
