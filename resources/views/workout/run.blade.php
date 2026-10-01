<x-layouts.app title="Séance" :bare="true">
    {{-- JSON_UNESCAPED_UNICODE : noms lisibles (« Développé ») ; JSON_HEX_TAG : impossible de fermer la balise script --}}
    <script type="application/json" id="workout-boot">{!! json_encode($boot, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_THROW_ON_ERROR) !!}</script>

    <div x-data="workout" x-cloak class="mx-auto flex min-h-screen max-w-xl flex-col px-4 pb-36 pt-[max(1rem,env(safe-area-inset-top))]">
        <header class="flex items-center justify-between gap-3 py-2">
            <a href="{{ route('home') }}" class="sys-label">Base</a>
            <span class="flex items-center gap-3">
                <span class="sys-num text-sm text-ice [text-shadow:0_0_10px_var(--glow-ice)]">● <span x-text="elapsed"></span></span>
                <button type="button" class="sys-label text-base" @click="showSettings = !showSettings" :aria-expanded="showSettings" aria-label="Réglages des signaux">⚙</button>
            </span>
            <button type="button" class="sys-btn !w-auto !px-4 !py-2 text-xs" x-show="!summary" :disabled="!canFinish || finishing" @click="finish()">
                <span x-text="finishing ? 'Envoi…' : 'Terminer'"></span>
            </button>
        </header>

        {{-- Réglages des signaux du chrono, retenus sur ce téléphone --}}
        <div class="sys-window mb-3 space-y-1 !p-3" x-show="showSettings" x-transition x-cloak>
            <p class="sys-label mb-1">Signaux du chrono</p>
            <template x-for="[key, label] in [['sound', 'Son (bips)'], ['vibration', 'Vibration'], ['countdown', 'Compte à rebours 3-2-1']]" :key="key">
                <button type="button" class="sys-row w-full text-left text-sm" @click="toggleSignal(key)" role="switch" :aria-checked="signals[key]">
                    <span x-text="label"></span>
                    <span class="sys-label" :class="{ '!text-ice': signals[key] }" x-text="signals[key] ? 'Activé' : 'Coupé'"></span>
                </button>
            </template>
        </div>

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
                            <button type="button" class="sys-label mt-1 underline decoration-ice/40 underline-offset-4" x-show="guideOf(current.exerciseId)" @click="openDemo(current.exerciseId)">Comment faire ?</button>
                            <p class="sys-num mt-1 text-xs uppercase tracking-wider text-steel" x-show="lastTimeText(current)">
                                Dernière fois · <span x-text="lastTimeText(current)"></span>
                            </p>
                            <p class="sys-num mt-1 text-xs uppercase tracking-wider text-ice" x-show="current.target"
                               x-text="current.target ? 'Objectif · ' + current.target.sets + ' × ' + current.target.repsMin + '–' + current.target.repsMax + ' · série ' + Math.min(current.sets.length + 1, current.target.sets) + '/' + current.target.sets : ''"></p>
                            <p class="sys-num mt-1 text-xs uppercase tracking-wider text-ice" x-show="current.prescribedKg"
                               x-text="current.prescribedKg ? 'Charge prescrite par TON EGO · ' + formatKg(current.prescribedKg) + (current.perDumbbell ? ' par haltère' : '') : ''"></p>
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
                                <label class="sys-label mb-1 block text-center" for="draft-weight" x-text="current.perDumbbell ? 'Kg par haltère' : 'Charge kg'">Charge kg</label>
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

                        <div class="sys-window !p-3 text-center text-sm" x-show="allDone">
                            <span class="font-bold text-ice">Toutes les séries sont faites.</span>
                            <button type="button" class="sys-btn mt-2" :disabled="finishing" @click="finish()">Terminer la séance</button>
                        </div>
                        <button type="button" class="sys-btn py-4 text-lg" :class="{ 'sys-btn--pulse': !resting, 'opacity-50': resting }" :disabled="resting" @click="validateSet()"
                                x-text="resting ? 'Repos en cours · ' + clock(restRemaining) : 'VALIDER LA SÉRIE'">VALIDER LA SÉRIE</button>
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
                                    <button type="button" class="flex min-w-0 flex-1 items-center justify-between py-1 text-left" @click="add(ex)">
                                        <span x-text="ex.name"></span>
                                        <span class="sys-label" x-show="boot.lastTime[ex.id]">déjà fait</span>
                                    </button>
                                    <button type="button" class="sys-chip !px-2.5" x-show="ex.guide" @click="openDemo(ex.id)" :aria-label="'Comment faire : ' + ex.name">?</button>
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

        {{-- Minuteur de repos : grand chrono plein écran, réductible --}}
        <div x-show="!summary && restRemaining > 0 && restExpanded" x-transition.opacity x-cloak
             class="fixed inset-0 z-40 flex flex-col items-center justify-center gap-6 bg-void/95 px-6 pb-[env(safe-area-inset-bottom)] text-center" role="timer" aria-live="off">
            <p class="sys-label">Repos</p>
            <div class="sys-ring sys-ring--xl" :style="`--p: ${restPercent}%`"><span x-text="clock(restRemaining)"></span></div>
            <p class="max-w-xs text-mist" x-show="current"
               x-text="!current ? '' : state.justAdvanced ? 'Exercice suivant : ' + current.name : (current.target ? 'Série ' + Math.min(current.sets.length + 1, current.target.sets) + '/' + current.target.sets + ' · ' : '') + current.name + ' · ' + formatKg(Number(String(current.draft.weightKg).replace(',', '.')) || 0) + ' × ' + current.draft.reps"></p>
            <div class="grid w-full max-w-xs grid-cols-3 gap-2">
                <button type="button" class="sys-btn sys-btn--ghost !px-2 !py-3" @click="lessRest()">−15 s</button>
                <button type="button" class="sys-btn sys-btn--ghost !px-2 !py-3" @click="moreRest()">+30 s</button>
                <button type="button" class="sys-btn !px-2 !py-3" @click="skipRest()">Passer</button>
            </div>
            <button type="button" class="sys-label" @click="restExpanded = false">Réduire</button>
        </div>
        <div x-show="!summary && restRemaining > 0 && !restExpanded" x-transition.opacity
             class="fixed inset-x-3 bottom-[max(0.75rem,env(safe-area-inset-bottom))] z-30 mx-auto max-w-xl">
            <div class="sys-window flex items-center gap-4 !p-3">
                <button type="button" class="sys-ring" :style="`--p: ${restPercent}%`" @click="restExpanded = true" aria-label="Agrandir le chrono"><span x-text="clock(restRemaining)"></span></button>
                <div class="flex-1">
                    <p class="sys-label">Repos</p>
                    <p class="mt-0.5 text-sm text-mist" x-text="state.justAdvanced && current ? 'Exercice suivant : ' + current.name : 'Prochaine série en préparation'">Prochaine série en préparation</p>
                    <div class="mt-2 flex gap-2">
                        <button type="button" class="sys-btn sys-btn--ghost !w-auto !px-3 !py-2 text-xs" @click="lessRest()">−15 s</button>
                        <button type="button" class="sys-btn sys-btn--ghost !w-auto !px-3 !py-2 text-xs" @click="moreRest()">+30 s</button>
                        <button type="button" class="sys-btn !w-auto !px-3 !py-2 text-xs" @click="skipRest()">Passer</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    {{-- Fenêtre « Comment faire ? » de la séance : mêmes contenus que le composant x-exercise-demo, liés à l'exercice choisi. --}}
    <template x-teleport="body">
        <div x-show="demo" x-transition.opacity x-cloak class="fixed inset-0 z-50 grid place-items-center overflow-y-auto bg-void/85 p-4"
             role="dialog" aria-modal="true" :aria-label="demo ? 'Comment faire : ' + demo.name : ''" @keydown.escape.window="closeDemo()" @click.self="closeDemo()">
            <template x-if="demo && demo.guide">
                <section class="sys-window w-full max-w-md" x-data="exerciseDemo(demo.slug)">
                    <header class="sys-window-title"><span class="sys-window-icon" aria-hidden="true">!</span><span x-text="demo.name.toUpperCase()"></span></header>
                    <p class="text-sm text-mist" x-text="demo.guide.muscles"></p>
                    <div class="relative mt-3 aspect-[240/215] border border-ice/15 [background:repeating-linear-gradient(0deg,rgba(90,216,255,.05)_0_1px,transparent_1px_22px),repeating-linear-gradient(90deg,rgba(90,216,255,.05)_0_1px,transparent_1px_22px)]">
                        <span class="sys-num absolute left-2 top-1.5 text-[11px] uppercase tracking-widest text-ice" x-text="phase"></span>
                        <span class="sys-num absolute right-2 top-1.5 text-[11px] text-mist" x-text="demo.guide.tempo"></span>
                        <svg x-ref="svg" :viewBox="viewBox" class="h-full w-full" role="img" :aria-label="'Démonstration animée : ' + demo.name"></svg>
                        <span class="sys-num absolute bottom-1 left-2 text-[10px] text-mist" x-text="note"></span>
                    </div>
                    <div class="mt-3 flex flex-wrap gap-2" role="group" aria-label="Vitesse de l'animation">
                        <button type="button" class="sys-chip" :class="{ 'sys-chip--active': !paused && speed === 1 }" @click="setSpeed(1)">Vitesse réelle</button>
                        <button type="button" class="sys-chip" :class="{ 'sys-chip--active': !paused && speed !== 1 }" @click="setSpeed(0.4)">Ralenti</button>
                        <button type="button" class="sys-chip" :class="{ 'sys-chip--active': paused }" @click="paused = !paused">Pause</button>
                    </div>
                    <ol class="mt-4 list-decimal space-y-2 pl-5 text-sm leading-relaxed marker:font-bold marker:text-ice">
                        <template x-for="step in demo.guide.steps"><li x-text="step"></li></template>
                    </ol>
                    <p class="mt-4 border-l-2 border-alert bg-alert/10 px-3 py-2 text-sm">
                        <span class="sys-num block text-[11px] font-bold tracking-widest text-alert">ERREUR À ÉVITER</span><span x-text="demo.guide.mistake"></span>
                    </p>
                    <x-sys.button variant="ghost" class="mt-4" @click="closeDemo()">Fermer</x-sys.button>
                </section>
            </template>
        </div>
    </template>
</x-layouts.app>
