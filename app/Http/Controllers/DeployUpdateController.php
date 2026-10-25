<?php

namespace App\Http\Controllers;

use Database\Seeders\ExerciseSeeder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

/**
 * Mise à jour de la base en production, sans ligne de commande (hébergement sans SSH) :
 * migrations puis catalogue d'exercices, protégées par le code DEPLOY_TOKEN.
 * Même code pour exporter les données de jeu (hébergement sans accès distant à la base).
 */
class DeployUpdateController
{
    private const EXPORTED_TABLES = [
        'users', 'exercises', 'programs', 'program_days', 'program_exercises', 'quests',
        'workouts', 'workout_sets', 'exercise_maxes', 'working_loads', 'personal_records',
        'xp_events', 'user_titles', 'system_notifications', 'crews', 'crew_user', 'challenges', 'challenge_user', 'home_attempts',
    ];

    public function show(): Response
    {
        $this->ensureEnabled();

        return $this->page('');
    }

    public function run(Request $request): Response
    {
        $this->ensureEnabled();

        if (! hash_equals((string) config('app.deploy_token'), (string) $request->input('token'))) {
            return $this->page('<p class="err">Code invalide.</p>', 403);
        }

        Artisan::call('migrate', ['--force' => true]);
        $output = Artisan::output();
        Artisan::call('db:seed', ['--class' => ExerciseSeeder::class, '--force' => true]);
        $output .= Artisan::output();

        return $this->page('<p class="ok">BASE À JOUR</p><pre>'.e(trim($output)).'</pre>');
    }

    public function export(Request $request): Response|JsonResponse
    {
        $this->ensureEnabled();

        if (! hash_equals((string) config('app.deploy_token'), (string) $request->input('token'))) {
            return $this->page('<p class="err">Code invalide.</p>', 403);
        }

        $tables = collect(self::EXPORTED_TABLES)->mapWithKeys(fn (string $table) => [$table => DB::table($table)->get()]);
        $tables['users'] = $tables['users']->map(fn (object $user) => collect($user)->except(['password', 'remember_token']));

        return response()->json($tables);
    }

    private function ensureEnabled(): void
    {
        abort_if(blank(config('app.deploy_token')), 404);
    }

    private function page(string $body, int $status = 200): Response
    {
        $html = <<<HTML
            <!doctype html><html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
            <meta name="robots" content="noindex"><title>Système · mise à jour</title>
            <style>body{background:#04060a;color:#d8f3ff;font-family:system-ui,sans-serif;max-width:32rem;margin:3rem auto;padding:0 1rem}
            h1{color:#5ad8ff;letter-spacing:.15em;font-size:1.1rem}input,button{font:inherit;padding:.7rem;width:100%;box-sizing:border-box;margin-top:.6rem;border-radius:.4rem}
            input{background:#0b1220;color:#d8f3ff;border:1px solid #3d6bff}button{background:#5ad8ff;color:#04060a;border:0;font-weight:700}
            .ok{color:#5ad8ff;font-weight:700}.err{color:#ff6b6b}pre{white-space:pre-wrap;font-size:.75rem;color:#9bb7c4}</style></head>
            <body><h1>METTRE À JOUR LA BASE</h1>{$body}
            <form method="post"><input type="password" name="token" placeholder="Code de déploiement" autocomplete="off" required><button>Lancer</button></form></body></html>
            HTML;

        return new Response($html, $status);
    }
}
