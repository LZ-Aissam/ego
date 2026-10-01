<x-layouts.app title="Séance" :bare="true">
    {{-- JSON_UNESCAPED_UNICODE : noms lisibles (« Développé ») ; JSON_HEX_TAG : impossible de fermer la balise script --}}
    <script type="application/json" id="workout-boot">{!! json_encode($boot, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_THROW_ON_ERROR) !!}</script>

    <div x-data="workout" x-cloak class="mx-auto flex min-h-screen max-w-xl flex-col px-4 pb-10 pt-[max(1rem,env(safe-area-inset-top))]">
        <header class="flex items-center justify-between gap-3 py-2">
            <a href="{{ route('home') }}" class="text-sm text-zinc-400">← Accueil</a>
            <span class="font-mono text-sm tabular-nums text-zinc-400" x-text="elapsed"></span>
            <button type="button" class="rounded-lg bg-lime-400 px-4 py-2 text-sm font-bold text-zinc-950 disabled:opacity-40"
                    x-show="!summary" :disabled="!canFinish || finishing" @click="finish()">
                <span x-text="finishing ? 'Envoi…' : 'Terminer'"></span>
            </button>
        </header>

        {{-- Résumé --}}
        <template x-if="summary">
            <section class="mt-6 space-y-4">
                <h1 class="text-2xl font-bold">Séance terminée 💪</h1>
                <template x-if="!summary.pending">
                    <div class="space-y-3">
                        <div class="grid grid-cols-3 gap-2 text-center">
                            <div class="card"><p class="text-xs text-zinc-400">Durée</p><p class="text-lg font-bold" x-text="summary.duration_minutes + ' min'"></p></div>
                            <div class="card"><p class="text-xs text-zinc-400">Séries</p><p class="text-lg font-bold" x-text="summary.sets_count"></p></div>
                            <div class="card"><p class="text-xs text-zinc-400">Volume</p><p class="text-lg font-bold" x-text="formatKg(summary.volume_kg)"></p></div>
                        </div>
                        <template x-if="summary.new_records.length">
                            <div class="card border-lime-400/50">
                                <p class="mb-2 font-bold text-lime-400">Nouveaux records 🏆</p>
                                <template x-for="r in summary.new_records" :key="r.exercise">
                                    <p class="text-sm"><span x-text="r.exercise"></span> — <span x-text="formatKg(r.weight_kg) + ' × ' + r.reps"></span></p>
                                </template>
                            </div>
                        </template>
                    </div>
                </template>
                <template x-if="summary.pending">
                    <div class="card text-sm text-zinc-300">
                        <span x-show="!summary.invalid">Séance enregistrée sur ton téléphone. Elle sera envoyée dès que la connexion revient.</span>
                        <span x-show="summary.invalid">Le serveur a refusé cette séance. Elle reste enregistrée sur ton téléphone.</span>
                    </div>
                </template>
                <a href="{{ route('home') }}" class="btn-primary">Retour à l'accueil</a>
            </section>
        </template>

        {{-- Séance --}}
        <template x-if="!summary">
            <section class="mt-2 flex flex-1 flex-col gap-4">
                <nav class="-mx-4 flex gap-2 overflow-x-auto px-4 pb-1" x-show="state.exercises.length">
                    <template x-for="(ex, i) in state.exercises" :key="i">
                        <button type="button" @click="go(i)"
                                class="shrink-0 rounded-full border px-3 py-1.5 text-sm"
                                :class="i === state.currentIndex ? 'border-lime-400 bg-lime-400/10 text-lime-300' : 'border-zinc-800 text-zinc-400'">
                            <span x-text="ex.name"></span>
                            <span class="ml-1 text-xs" x-text="ex.sets.length ? '(' + ex.sets.length + ')' : ''"></span>
                        </button>
                    </template>
                </nav>

                <template x-if="current && !picking">
                    <div class="card space-y-4">
                        <div>
                            <h1 class="text-2xl font-bold" x-text="current.name"></h1>
                            <p class="mt-1 text-sm text-zinc-400" x-show="lastTimeText(current)">
                                Dernière fois : <span x-text="lastTimeText(current)"></span>
                            </p>
                            <p class="mt-1 text-sm text-zinc-500" x-show="current.isBodyweight">Poids du corps — indique la charge ajoutée (0 si aucune).</p>
                        </div>

                        <ol class="space-y-1" x-show="current.sets.length">
                            <template x-for="(set, i) in current.sets" :key="i">
                                <li class="flex justify-between rounded-lg bg-zinc-800/60 px-3 py-2 text-sm">
                                    <span class="text-zinc-400" x-text="'Série ' + (i + 1)"></span>
                                    <span class="font-semibold" x-text="formatKg(set.weightKg) + ' × ' + set.reps"></span>
                                </li>
                            </template>
                        </ol>

                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="mb-1 block text-center text-xs text-zinc-400" for="draft-weight">Charge (kg)</label>
                                <div class="flex items-center gap-1">
                                    <button type="button" class="h-12 w-12 shrink-0 rounded-xl bg-zinc-800 text-2xl" @click="adjust('weightKg', -2.5)" aria-label="Moins 2,5 kg">−</button>
                                    <input id="draft-weight" type="text" inputmode="decimal" class="input h-12 min-w-0 px-1 text-center text-lg font-bold" x-model="current.draft.weightKg">
                                    <button type="button" class="h-12 w-12 shrink-0 rounded-xl bg-zinc-800 text-2xl" @click="adjust('weightKg', 2.5)" aria-label="Plus 2,5 kg">+</button>
                                </div>
                            </div>
                            <div>
                                <label class="mb-1 block text-center text-xs text-zinc-400" for="draft-reps">Répétitions</label>
                                <div class="flex items-center gap-1">
                                    <button type="button" class="h-12 w-12 shrink-0 rounded-xl bg-zinc-800 text-2xl" @click="adjust('reps', -1)" aria-label="Une répétition de moins">−</button>
                                    <input id="draft-reps" type="text" inputmode="numeric" class="input h-12 min-w-0 px-1 text-center text-lg font-bold" x-model="current.draft.reps">
                                    <button type="button" class="h-12 w-12 shrink-0 rounded-xl bg-zinc-800 text-2xl" @click="adjust('reps', 1)" aria-label="Une répétition de plus">+</button>
                                </div>
                            </div>
                        </div>

                        <button type="button" class="btn-primary py-4 text-lg" @click="validateSet()">Valider la série</button>
                        <button type="button" class="w-full text-sm text-zinc-500" x-show="current.sets.length" @click="undoSet()">Annuler la dernière série</button>
                    </div>
                </template>

                {{-- Choix d'un exercice --}}
                <template x-if="picking">
                    <div class="card space-y-3">
                        <div class="flex items-center justify-between">
                            <h2 class="text-lg font-bold">Ajouter un exercice</h2>
                            <button type="button" class="text-sm text-zinc-400" x-show="state.exercises.length" @click="picking = false">Fermer</button>
                        </div>
                        <input type="search" class="input" placeholder="Rechercher (ex. squat)" x-model="search">
                        <ul class="max-h-[50vh] divide-y divide-zinc-800 overflow-y-auto">
                            <template x-for="ex in filtered" :key="ex.id">
                                <li>
                                    <button type="button" class="flex w-full items-center justify-between py-3 text-left" @click="add(ex)">
                                        <span x-text="ex.name"></span>
                                        <span class="text-xs text-zinc-500" x-text="boot.lastTime[ex.id] ? 'déjà fait' : ''"></span>
                                    </button>
                                </li>
                            </template>
                        </ul>
                    </div>
                </template>

                <button type="button" class="btn-secondary" x-show="!picking" @click="picking = true">+ Ajouter un exercice</button>

                <div class="mt-auto pt-6 text-center">
                    <button type="button" class="text-sm text-red-400" @click="discard()"
                            x-text="confirmDiscard ? 'Confirmer l\'abandon (la séance sera perdue)' : 'Abandonner la séance'"></button>
                </div>
            </section>
        </template>

        {{-- Minuteur de repos --}}
        <div x-show="!summary && restRemaining > 0" x-transition.opacity
             class="fixed inset-x-0 bottom-0 z-30 border-t border-zinc-800 bg-zinc-950/95 px-4 pb-[max(1rem,env(safe-area-inset-bottom))] pt-4 backdrop-blur">
            <div class="mx-auto flex max-w-xl items-center justify-between gap-3">
                <div>
                    <p class="text-xs uppercase tracking-wide text-zinc-400">Repos</p>
                    <p class="font-mono text-4xl font-black tabular-nums text-lime-400" x-text="clock(restRemaining)"></p>
                </div>
                <div class="flex gap-2">
                    <button type="button" class="rounded-xl bg-zinc-800 px-4 py-3 font-semibold" @click="moreRest()">+30 s</button>
                    <button type="button" class="rounded-xl bg-lime-400 px-4 py-3 font-bold text-zinc-950" @click="skipRest()">Passer</button>
                </div>
            </div>
        </div>
    </div>
</x-layouts.app>
