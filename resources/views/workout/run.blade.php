<x-layouts.app title="Séance" :bare="true">
    {{-- JSON_UNESCAPED_UNICODE : noms lisibles (« Développé ») ; JSON_HEX_TAG : impossible de fermer la balise script --}}
    <script type="application/json" id="workout-boot">{!! json_encode($boot, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_THROW_ON_ERROR) !!}</script>

    <div x-data="workout" x-cloak class="mx-auto flex min-h-screen max-w-xl flex-col px-4 pb-36 pt-[max(1rem,env(safe-area-inset-top))]">
        <header class="flex items-center justify-between gap-3 py-2">
            <a href="{{ route('home') }}" class="sys-label">Base</a>
            <span class="sys-num text-sm text-ice [text-shadow:0_0_10px_var(--glow-ice)]">● <span x-text="elapsed"></span></span>
            <button type="button" class="sys-btn !w-auto !px-4 !py-2 text-xs" x-show="!summary" :disabled="!canFinish || finishing" @click="finish()">
                <span x-text="finishing ? 'Envoi…' : 'Terminer'"></span>
            </button>
        </header>

        {{-- Fin de séance --}}
        <template x-if="summary">
            <section class="mt-6 space-y-4">
                <template x-if="!summary.pending">
                    <div class="sys-window">
                        <header class="sys-window-title"><span class="sys-window-icon" aria-hidden="true">!</span>SÉANCE ENREGISTRÉE</header>
                        <div class="grid grid-cols-3 gap-2 text-center">
                            <div><p class="sys-label">Durée</p><p class="sys-num mt-1 text-lg font-bold" x-text="summary.duration_minutes + ' min'"></p></div>
                            <div><p class="sys-label">Séries</p><p class="sys-num mt-1 text-lg font-bold" x-text="summary.sets_count"></p></div>
                            <div><p class="sys-label">Volume</p><p class="sys-num mt-1 text-lg font-bold text-ice" x-text="formatKg(summary.volume_kg)"></p></div>
                        </div>
                        <p class="sys-num mt-3 text-center text-lg font-extrabold text-ice [text-shadow:0_0_14px_var(--glow-ice)]" x-show="summary.xp_gained > 0" x-text="'+' + summary.xp_gained + ' XP'"></p>
                    </div>
                </template>
                <template x-if="summary.pending">
                    <div class="sys-window" :class="summary.invalid && 'sys-window--alert'">
                        <header class="sys-window-title"><span class="sys-window-icon" aria-hidden="true">!</span>EN ATTENTE</header>
                        <p class="text-sm text-mist" x-show="!summary.invalid">Séance enregistrée sur ton téléphone. Elle sera envoyée dès que la connexion revient.</p>
                        <p class="text-sm text-mist" x-show="summary.invalid">Le serveur a refusé cette séance. Elle reste enregistrée sur ton téléphone.</p>
                    </div>
                </template>
                <a href="{{ route('home') }}" class="sys-btn">Retour à la base</a>
            </section>
        </template>

        {{-- Séance --}}
        <template x-if="!summary">
            <section class="mt-2 flex flex-1 flex-col gap-4">
                <nav class="-mx-4 flex gap-2 overflow-x-auto px-4 pb-1" x-show="state.exercises.length">
                    <template x-for="(ex, i) in state.exercises" :key="i">
                        <button type="button" @click="go(i)" class="sys-chip" :class="i === state.currentIndex && 'sys-chip--active'">
                            <span x-text="ex.name"></span><span class="ml-1" x-text="ex.sets.length ? '(' + ex.sets.length + ')' : ''"></span>
                        </button>
                    </template>
                </nav>

                <template x-if="current && !picking">
                    <div class="sys-window space-y-4">
                        <div>
                            <h1 class="text-2xl font-bold leading-tight" x-text="current.name"></h1>
                            <p class="sys-num mt-1 text-xs uppercase tracking-wider text-steel" x-show="lastTimeText(current)">
                                Dernière fois · <span x-text="lastTimeText(current)"></span>
                            </p>
                            <p class="mt-1 text-sm text-mist" x-show="current.isBodyweight">Poids du corps — indique la charge ajoutée (0 si aucune).</p>
                        </div>

                        <ol class="space-y-1.5" x-show="current.sets.length">
                            <template x-for="(set, i) in current.sets" :key="i">
                                <li class="sys-num flex justify-between bg-white/[.04] px-3 py-2 text-sm [clip-path:var(--sys-cut)]">
                                    <span class="text-steel" x-text="'SÉRIE ' + String(i + 1).padStart(2, '0')"></span>
                                    <span class="font-bold text-ice" x-text="formatKg(set.weightKg) + ' × ' + set.reps"></span>
                                </li>
                            </template>
                        </ol>

                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="sys-label mb-1 block text-center" for="draft-weight">Charge kg</label>
                                <div class="flex items-center gap-1">
                                    <button type="button" class="sys-step" @click="adjust('weightKg', -2.5)" aria-label="Moins 2,5 kg">−</button>
                                    <input id="draft-weight" type="text" inputmode="decimal" class="sys-input sys-num min-w-0 px-1 text-center text-xl font-extrabold" x-model="current.draft.weightKg">
                                    <button type="button" class="sys-step" @click="adjust('weightKg', 2.5)" aria-label="Plus 2,5 kg">+</button>
                                </div>
                            </div>
                            <div>
                                <label class="sys-label mb-1 block text-center" for="draft-reps">Reps</label>
                                <div class="flex items-center gap-1">
                                    <button type="button" class="sys-step" @click="adjust('reps', -1)" aria-label="Une répétition de moins">−</button>
                                    <input id="draft-reps" type="text" inputmode="numeric" class="sys-input sys-num min-w-0 px-1 text-center text-xl font-extrabold" x-model="current.draft.reps">
                                    <button type="button" class="sys-step" @click="adjust('reps', 1)" aria-label="Une répétition de plus">+</button>
                                </div>
                            </div>
                        </div>

                        <button type="button" class="sys-btn sys-btn--pulse py-4 text-lg" @click="validateSet()">VALIDER LA SÉRIE</button>
                        <button type="button" class="sys-label w-full text-center" x-show="current.sets.length" @click="undoSet()">Annuler la dernière série</button>
                    </div>
                </template>

                <template x-if="picking">
                    <div class="sys-window space-y-3">
                        <header class="sys-window-title"><span class="sys-window-icon" aria-hidden="true">!</span>CHOISIR UN EXERCICE</header>
                        <div class="flex justify-end" x-show="state.exercises.length">
                            <button type="button" class="sys-label" @click="picking = false">Fermer</button>
                        </div>
                        <input type="search" class="sys-input" placeholder="Rechercher (ex. squat)" x-model="search">
                        <ul class="max-h-[50vh] overflow-y-auto">
                            <template x-for="ex in filtered" :key="ex.id">
                                <li class="sys-row">
                                    <button type="button" class="flex w-full items-center justify-between py-1 text-left" @click="add(ex)">
                                        <span x-text="ex.name"></span>
                                        <span class="sys-label" x-show="boot.lastTime[ex.id]">déjà fait</span>
                                    </button>
                                </li>
                            </template>
                        </ul>
                    </div>
                </template>

                <button type="button" class="sys-btn sys-btn--ghost" x-show="!picking" @click="picking = true">+ Ajouter un exercice</button>

                <div class="mt-auto pt-6 text-center">
                    <button type="button" class="text-sm text-alert" @click="discard()"
                            x-text="confirmDiscard ? 'Confirmer l\'abandon (la séance sera perdue)' : 'Abandonner la séance'"></button>
                </div>
            </section>
        </template>

        {{-- Minuteur de repos --}}
        <div x-show="!summary && restRemaining > 0" x-transition.opacity
             class="fixed inset-x-3 bottom-[max(0.75rem,env(safe-area-inset-bottom))] z-30 mx-auto max-w-xl">
            <div class="sys-window flex items-center gap-4 !p-3">
                <div class="sys-ring" :style="`--p: ${restPercent}%`"><span x-text="clock(restRemaining)"></span></div>
                <div class="flex-1">
                    <p class="sys-label">Repos</p>
                    <p class="mt-0.5 text-sm text-mist">Prochaine série en préparation</p>
                    <div class="mt-2 flex gap-2">
                        <button type="button" class="sys-btn sys-btn--ghost !w-auto !px-3 !py-2 text-xs" @click="moreRest()">+30 s</button>
                        <button type="button" class="sys-btn !w-auto !px-3 !py-2 text-xs" @click="skipRest()">Passer</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-layouts.app>
