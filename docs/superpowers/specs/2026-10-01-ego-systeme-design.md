# EGO « Système » — Spécification de conception

**Date :** 2026-10-01
**Statut :** en relecture
**Complète :** `2026-10-01-ego-design.md` (spec de base). En cas de conflit, ce document l'emporte. Le générateur de programmes (§8 de la spec de base) reste valable, avec l'ajout du §5.1 ci-dessous.

## 1. Intention

Donner à EGO une identité visuelle futuriste et premium (« comme si on avait mis 100 k€ dans le design »), inspirée de l'interface « Système » des webtoons de type *leveling*. Le pratiquant est un **joueur** : une interface holographique lui dit quoi faire pour passer au niveau suivant, lui assigne des quêtes, le récompense et le sanctionne.

On reprend le **style** (fenêtres système, quêtes, niveaux, rangs), mais **aucun nom, logo, personnage ou texte** d'une œuvre existante. EGO garde sa propre identité.

### Critères de réussite

- Chaque écran donne une impression de produit haut de gamme cohérent : mêmes couleurs, typographies, matières et mouvements partout.
- On comprend en un coup d'œil, sur l'accueil, son niveau, ce que le Système attend aujourd'hui et ce qu'on y gagne.
- La progression (XP, niveaux, rangs, statistiques, titres) reflète l'entraînement réel et ne se triche pas facilement.
- Une quête ratée motive à revenir au lieu de décourager : quête de pénalité, mode pause.
- L'app reste fluide sur un téléphone d'entrée de gamme, et s'affiche correctement hors ligne (polices comprises).
- Toujours aucun cron ni worker obligatoire.

### Hors périmètre

Points de statistiques à attribuer librement, perte d'XP, statut de pénalité public, sons d'interface, particules et effets « glitch » (niveau d'animation « Spectaculaire »), classement et défis (étape 3 de la spec de base).

## 2. Décisions validées

| Sujet | Décision |
|---|---|
| Direction artistique | **Neon Grid**, couleur **Ice** (cyan `#5AD8FF` → bleu `#3D6BFF`) |
| Animations | Niveau **« Vivant »** ; désactivées si `prefers-reduced-motion` |
| Langage visuel | **Fenêtres « Système »** (verre bleu nuit, coins coupés, liseré néon, titres espacés) |
| Approche technique | Système de design maison : jetons Tailwind 4 + composants Blade + CSS ; **aucune nouvelle dépendance JS** |
| Gamification | **Jeu complet** : XP, niveaux, rangs, statistiques, titres, quêtes, pénalités |
| Statistiques | **Calculées** à partir des vraies performances (pas de points à attribuer) |
| Échec de quête | **Quête de pénalité** le lendemain ; la série tombe à 0 si elle est ratée aussi |
| Mode pause | Oui : il gèle quêtes, pénalités et série |

## 3. Découpage en livraisons

Chaque livraison a son propre plan d'implémentation et donne une version utilisable.

1. **Livraison A — Refonte « Système »** : système de design (§4) appliqué à tous les écrans existants, et composant de notifications système, utilisé dès cette livraison pour les nouveaux records.
2. **Livraison B — Progression du joueur** : XP, niveaux, rangs, statistiques Force, Endurance et Progression, titres sans lien avec les quêtes, notifications de niveau, de rang et de titre, fiche du joueur (§5.2 à §5.5).
3. **Livraison C — Le Système te guide** : générateur de programmes (spec de base §8 + §5.1), quête du jour, quête de pénalité, série de jours, mode pause, stat Régularité, titres liés aux quêtes (§5.6 à §5.9).

## 4. Système de design

### 4.1 Jetons (Tailwind 4 `@theme`)

| Jeton | Valeur | Usage |
|---|---|---|
| `--color-void` | `#04060A` | fond |
| `--color-abyss` | `#0B1A2C` | haut du dégradé de fond, fond des fenêtres |
| `--color-ice` | `#5AD8FF` | néon principal |
| `--color-ice-deep` | `#3D6BFF` | fin des dégradés |
| `--color-frost` | `#E8F6FF` | texte principal |
| `--color-mist` | `#9BB7C4` | texte secondaire |
| `--color-steel` | `#6F8F9A` | libellés techniques, texte désactivé |
| `--color-alert` | `#FF4D6D` | erreurs, pénalité |
| `--color-amber` | `#FFB547` | hors ligne, avertissements |
| `--glow-ice` | `rgba(90,216,255,.55)` | ombres lumineuses |
| `--font-sans` | Space Grotesk | titres, textes |
| `--font-mono` | JetBrains Mono | chiffres, libellés techniques |

- **Polices auto-hébergées** via `@fontsource/space-grotesk` et `@fontsource/jetbrains-mono` (graisses 400, 500, 700 et 500, 700, 800), regroupées par Vite dans `public/build`. Elles sont donc mises en cache par le service worker et disponibles hors ligne. Aucun appel à Google Fonts.
- **Fond global** : dégradé radial `abyss` → `void`, grille holographique de 22 px qui s'efface vers le bas, et halo `ice` en haut à gauche.
- **Coins coupés** : `clip-path` octogonal à 6 px, sur les fenêtres et les boutons principaux.
- Tout chiffre (charge, répétitions, chrono, XP, volume, niveau) est en `font-mono` avec `tabular-nums`.

### 4.2 Composants Blade (`resources/views/components/sys/`)

| Composant | Rôle |
|---|---|
| `<x-sys.window title="STATUT" :variant="'default'|'alert'">` | Fenêtre système : verre, coins coupés, liseré, titre centré espacé avec icône `!`. La variante `alert` est rouge (pénalité, erreur). |
| `<x-sys.button>` / `<x-sys.button variant="ghost">` | Bouton principal en dégradé néon, ou secondaire avec liseré. Il s'enfonce au toucher. |
| `<x-sys.label>` | Libellé technique `// TEXTE` en mono, majuscules espacées. |
| `<x-sys.stat :value :label :max>` | Statistique : nom, valeur et barre. |
| `<x-sys.xp-bar :user>` | Barre d'XP, avec niveau actuel et suivant. |
| `<x-sys.ring :seconds>` | Anneau de progression (repos). |
| `<x-sys.chip :active>` | Puce (exercices de la séance, filtres). |
| `<x-sys.field>` | Champ de formulaire stylé, avec son message d'erreur. |
| `<x-sys.notifications>` | Conteneur des notifications système (§4.4). |

La navigation devient une **barre flottante en verre** en bas sur mobile, et une barre latérale sur PC (≥ 1024 px). Elle garde 4 onglets ; « Communauté » est renommé **« Crew »**.

### 4.3 Animations « Vivant » (CSS ; JS uniquement pour les compteurs et la file de notifications)

- La grille défile lentement (8 s, en boucle) et le halo « respire » (4 s).
- Le bouton principal pulse (lueur, 2 s).
- Les fenêtres et les cartes **s'ouvrent en flash holographique**, en cascade de 150 ms : dépliage vertical, puis éclat lumineux.
- Les compteurs défilent jusqu'à leur valeur (volume, XP, statistiques) ; c'est le petit module JS `resources/js/ui/count-up.js`.
- Un appui enfonce le bouton (`scale(.96)`).
- L'anneau de repos se vide en continu.
- Un nouveau record déclenche un éclat lumineux (8 rayons), un badge « NEW PR » et une vibration.
- `@media (prefers-reduced-motion: reduce)` coupe toutes les boucles et transitions : les éléments apparaissent directement à leur état final.

### 4.4 Notifications système

- Ce sont des fenêtres modales au centre de l'écran, sur un fond assombri et flouté, avec vibration `[60, 40, 60]`. On les ferme en touchant n'importe où ; si plusieurs arrivent, elles s'enchaînent.
- **Types** :

| Type | Message |
|---|---|
| `record` | « Nouveau record — Développé couché 65 kg × 5 » |
| `level_up` | « Tu as monté de niveau ! NIVEAU 13 » |
| `rank_up` | Version plus spectaculaire, avec un grand libellé de rang |
| `title` | « Titre débloqué : *Titan* » |
| `quest_done` | « Quête accomplie. +240 XP » |
| `quest_failed` | Variante `alert` |
| `penalty` | Variante `alert` |
| `pause_on` / `pause_off` | Suspension et reprise des quêtes |

- **Provenance** :
  - les notifications **côté serveur** sont stockées dans une table `system_notifications` (§6), affichées au chargement de page suivant, puis marquées vues ;
  - celles **produites par une synchronisation de séance** arrivent dans la réponse JSON de `/workouts/sync` et s'affichent immédiatement à la place du résumé actuel.

### 4.5 Écrans redessinés (livraison A)

| Écran | Contenu |
|---|---|
| Connexion / inscription | Fenêtre système `IDENTIFICATION` ou `ÉVEIL`, logo EGO en néon, champs stylés |
| Accueil (« console du joueur ») | Livraison A : salutation, bouton « Lancer la séance » et fenêtre `DERNIERS RECORDS`. La fenêtre `STATUT` arrive en livraison B, et la fenêtre `QUÊTE DU JOUR` en livraison C. |
| Séance en cours | Comme la maquette validée (`neon-couleur` Ice) : puces d'exercices, nom de l'exercice, « dernière fois », séries faites, champs +/− géants en mono, bouton néon, minuteur de repos en bas avec anneau. En fin de séance, une notification système remplace le résumé simple : `SÉANCE TERMINÉE`, durée, séries, volume, puis les notifications de records. |
| Historique, détail d'une séance, records, progression | Fenêtres système. Le graphique de progression passe en néon : ligne `ice` lumineuse et points. |
| Profil (« fiche du joueur ») | Livraison A : identité, morphologie, déconnexion. En livraison B s'ajoutent les statistiques et les titres. |
| Programme, Crew | Pages « bientôt » au nouveau style |

## 5. Gamification

### 5.1 Ajout au générateur (livraison C)

Le questionnaire demande les **jours d'entraînement de la semaine** (cases lundi → dimanche, entre 2 et 6 jours) au lieu d'un simple nombre. `days_per_week` = nombre de jours cochés, et `training_weekdays` = liste ISO (1 = lundi … 7 = dimanche). Le reste du générateur suit la spec de base §8.

### 5.2 XP (livraison B, sauf mention)

| Source | XP |
|---|---|
| Séance terminée d'au moins 3 séries | `50 + 5 × min(séries, 30)` (200 au maximum) |
| Nouveau record (au sens de `WorkoutSync` : bat un record existant) | +50 chacun, 3 au maximum par séance |
| Quête du jour accomplie (C) | `200 × (1 + min(série_avant, 5) × 0,10)` arrondi, soit 300 au maximum |
| Quête de pénalité réussie (C) | +50 |

- **Plafond** : 2 séances XP-éligibles par jour calendaire (Europe/Paris), les 2 premières terminées. Une séance de moins de 3 séries ne rapporte rien.
- **Journal** : table `xp_events` (§6), avec unicité sur `(user_id, source, source_id)`. Attribuer deux fois la même source est donc impossible. `users.xp_total` est un cache recalculable depuis ce journal.
- **Rétroactivité** : une migration de données attribue l'XP des séances et records déjà enregistrés. Elle ne crée **aucune** notification.

### 5.3 Niveaux et rangs (livraison B)

- **XP pour passer du niveau n à n+1** : `xpToNext(n) = round(100 × n^1,3)`. Niveau 1 au départ. `levelForXp(xp)` renvoie le plus grand niveau dont le cumul est ≤ xp.
- **Rangs** :

| Rang | Niveaux |
|---|---|
| E | 1–9 |
| D | 10–19 |
| C | 20–29 |
| B | 30–44 |
| A | 45–59 |
| S | 60+ |

- **Notifications** : chaque niveau gagné produit une notification `level_up` (s'il y en a plusieurs d'un coup, une seule, avec le niveau final) ; un nouveau rang produit une notification `rank_up`.

### 5.4 Statistiques (calculées, valeurs entières ≥ 0, recalculées à l'affichage)

| Stat | Calcul | Livraison |
|---|---|---|
| **FORCE** | moyenne des 3 meilleurs `best_e1rm_kg` sur les exercices `compound` / poids de corps actuel × 50. Nulle (affichée « — » avec un lien vers le profil) si le poids est inconnu. | B |
| **ENDURANCE** | répétitions totales des 28 derniers jours / 50 | B |
| **PROGRESSION** | records **battus** des 28 derniers jours × 10. Ils sont comptés grâce aux `xp_events` de source `record`. | B |
| **RÉGULARITÉ** | % des quêtes (entraînement + repos) accomplies parmi celles échues sur les 28 derniers jours, jours de pause exclus | C |

Les barres sont graduées sur 100, mais une valeur peut dépasser 100 (la barre est alors pleine et lumineuse).

### 5.5 Titres

- **Catalogue** dans le code (`App\Game\Titles`), clé → nom + condition :

| Clé | Nom | Condition | Livraison |
|---|---|---|---|
| `awakened` | L'Éveillé | 1 séance | B |
| `record_breaker` | Briseur de records | 10 records battus | B |
| `centurion` | Centurion | 100 séances | B |
| `titan` | Titan | 100 000 kg de volume cumulé | B |
| `rank_c` / `rank_b` / `rank_a` | Chasseur de rang C / B / A | atteindre ce rang | B |
| `monarch` | Monarque | rang S | B |
| `steady` | Assidu | série de 7 jours | C |
| `unstoppable` | Inarrêtable | série de 30 jours | C |
| `revenant` | Revenant | 1 quête de pénalité réussie | C |

- **Déblocage** : les titres débloqués sont stockés dans `user_titles`. Ils sont vérifiés après chaque gain d'XP et chaque évaluation de quête, et produisent une notification `title`.
- **Titre équipé** : le joueur en choisit un (`users.equipped_title`) depuis sa fiche ; il est affiché sous son prénom. Par défaut, c'est le premier titre débloqué.
- **Rétroactivité** : la migration de rattrapage débloque les titres mérités, sans notification.

### 5.6 Quête du jour (livraison C)

- **Création** (`QuestScheduler::sync(User, Carbon $today)`, appelé à chaque chargement de page authentifiée, idempotent) :
  - s'il n'y a ni programme actif ni pause en cours : aucune quête ;
  - sinon, pour aujourd'hui :
    - jour d'entraînement → quête `training` liée au **jour de programme suivant**, c'est-à-dire celui qui suit, dans l'ordre `position`, le jour de la dernière quête `training` accomplie (le premier jour s'il n'y en a pas). Ses objectifs (exercice, séries prévues, plage de répétitions) sont **copiés** dans la quête, pour qu'une modification ultérieure du programme ne l'altère pas ;
    - sinon → quête `rest`, accomplie d'office.
- **Accomplissement** : une quête `training` est accomplie dès qu'une séance du **même jour calendaire** fait, pour chaque objectif, au moins `séries prévues` séries sur cet exercice ou sur un exercice qui partage un emplacement (`slot`) avec lui. Ce contrôle est fait à chaque `/workouts/sync` ; l'XP est attribuée et une notification `quest_done` est renvoyée.
- **Échéance** : à minuit (Europe/Paris), une quête `training` non accomplie devient `failed`. Ce passage est constaté par `QuestScheduler::sync` au premier chargement suivant, pour **chaque jour écoulé**, avec une notification `quest_failed` par quête ratée.
- **« Accepter la quête »** ouvre `/workout/quest/{quest}` : l'écran de séance s'ouvre prérempli avec les exercices, les séries, la plage de répétitions (la valeur du brouillon = « dernière fois », sinon `reps_min`) et les temps de repos du programme. La séance envoyée porte `quest_id`.

### 5.7 Quête de pénalité (livraison C)

- **Déclenchement** : une quête `training` ratée le jour J crée une quête `penalty` pour J+1. Il n'y en a jamais plus d'une par jour, et une pénalité ratée n'en déclenche pas d'autre.
- **Circuit au poids du corps** selon le niveau d'entraînement (`users.level`) :

| Niveau | Circuit |
|---|---|
| débutant | 3 × 10 pompes, 3 × 15 squats au poids du corps, 3 × 15 crunchs |
| intermédiaire | 3 × 20, 3 × 25, 3 × 25 |
| avancé | 4 × 25, 4 × 30, 4 × 30 |

- **Réussite** (même règle d'accomplissement) avant minuit de J+1 : la série est conservée, +50 XP, et le titre `revenant` la première fois.
- **Échec** : la série retombe à 0 (notification `penalty` variante échec).
- **Affichage** : une fenêtre `PÉNALITÉ` en variante `alert` sur l'accueil, au-dessus de la quête du jour.

### 5.8 Série de jours (livraison C)

- `users.streak_days` = nombre de jours consécutifs, jusqu'à hier inclus, dont la quête du jour (`training` ou `rest`) est accomplie ; on y ajoute aujourd'hui si sa quête est déjà accomplie. `users.best_streak` garde le maximum.
- Une quête `training` ratée **ne remet pas** la série à zéro tout de suite : c'est l'échec de la pénalité du lendemain qui le fait. Si la pénalité est réussie, le jour raté est compté comme « sauvé », et la série continue sans l'augmenter.
- Les jours de pause sont neutres : ils n'augmentent ni ne cassent la série.

### 5.9 Mode pause (livraison C)

- Dans la fiche du joueur, le bouton « Suspendre les quêtes » ouvre un choix de date de fin facultative.
- `users.paused_at` (date) et `users.paused_until` (date nullable).
- Pendant la pause : aucune quête n'est créée, aucun échec n'est constaté, et une éventuelle pénalité en attente est annulée.
- **Fin** : manuelle (« Reprendre ») ou automatique le lendemain de `paused_until`. Notifications `pause_on` et `pause_off`.

## 6. Modèle de données (ajouts)

| Table / colonnes | Livraison |
|---|---|
| `users` + `xp_total` (int, défaut 0), `equipped_title` (string nullable) | B |
| `xp_events` : `user_id`, `source` (`workout`/`record`/`quest`/`penalty`), `source_id` (bigint), `amount` (int), `created_at` ; unique `(user_id, source, source_id)` | B |
| `user_titles` : `user_id`, `title_key`, `unlocked_at` ; unique `(user_id, title_key)` | B |
| `system_notifications` : `user_id`, `type`, `payload` (json), `seen_at` (nullable), `created_at` | A (table) / B–C (usages) |
| `programs`, `program_days`, `program_exercises` : selon la spec de base §6 | C |
| `users` + `training_weekdays` (json), `streak_days` (int, défaut 0), `best_streak` (int, défaut 0), `paused_at` (date nullable), `paused_until` (date nullable) | C |
| `workouts` + `quest_id` (nullable, FK `quests`), `program_day_id` (nullable) | C |
| `quests` : `user_id`, `date`, `type` (`training`/`rest`/`penalty`), `program_day_id` (nullable), `objectives` (json : `[{exercise_id, slot, sets, reps_min, reps_max, rest_seconds}]`), `status` (`pending`/`done`/`failed`/`cancelled`), `completed_at` (nullable) ; unique `(user_id, date, type)` | C |

## 7. Services (PHP pur, testables)

| Service | Responsabilité | Livraison |
|---|---|---|
| `App\Game\Leveling` | `xpToNext(int $level): int`, `levelForXp(int $xp): int`, `rankForLevel(int $level): string`, `progress(int $xp): array{level, rank, into, needed}` | B |
| `App\Game\XpLedger` | `award(User, string $source, int $sourceId, int $amount): bool` (idempotent, met à jour `xp_total`), `workoutXp(Workout): int` | B |
| `App\Game\PlayerStats` | `for(User): array{force, endurance, progression, regularite}` | B (+C pour régularité) |
| `App\Game\Titles` | catalogue + `check(User): list<string>` (nouvelles clés débloquées) | B |
| `App\Game\GameEvents` | point d'entrée appelé après `WorkoutSync` : XP de la séance et des records, puis niveau, rang et titres ; renvoie la liste des notifications | B |
| `App\Game\QuestScheduler` | `sync(User, Carbon $today): list<notification>` : crée les quêtes, constate les échecs, crée les pénalités, gère la pause, met à jour la série | C |
| `App\Game\QuestEvaluator` | `isAccomplished(Quest, Collection $workoutsOfDay): bool` | C |
| `App\Game\PenaltyCircuit` | `for(?string $level): list<objective>` | C |
| `App\Services\ProgramGenerator` | spec de base §8 | C |

`POST /workouts/sync` ajoute à sa réponse : `notifications: [{type, payload}]`, `xp_gained`, `player: {level, rank, xp_into, xp_needed}`.

## 8. Gestion des erreurs et cas limites

- **Rétroactivité** : la migration de rattrapage est idempotente (journal unique) ; la relancer ne change rien.
- **Séance synchronisée en retard**, par exemple une séance faite hors ligne hier et envoyée aujourd'hui : son XP est attribuée normalement. Elle peut accomplir **la quête du jour de la séance** (date de `finished_at`), même si cette quête a déjà été constatée « ratée » : elle repasse alors `done`, la pénalité du lendemain est annulée si elle n'est pas encore accomplie, et la série est recalculée.
- **Changement d'heure** : tous les jours calendaires sont calculés en Europe/Paris à partir de `finished_at` local.
- **Programme supprimé ou régénéré** : les quêtes déjà créées gardent leur copie des objectifs.
- **Hors ligne** : le chargement de page qui appelle `QuestScheduler::sync` nécessite le réseau. Hors ligne, l'accueil en cache montre le dernier état connu, et la séance reste utilisable.
- **Accessibilité** : contrastes texte/fond ≥ 4,5:1 (`frost` et `mist` sur `void`) ; la lueur n'est jamais le seul indicateur d'état (on garde toujours un libellé ou une icône) ; `prefers-reduced-motion` est respecté.

## 9. Tests

- **Unitaires (Pest)** :
  - `Leveling` : courbe, bornes de rangs, `levelForXp` aux frontières ;
  - `XpLedger::workoutXp` : seuil de 3 séries, plafond de 30 séries ;
  - `PlayerStats` : poids inconnu, fenêtre de 28 jours ;
  - `Titles` : chaque condition ;
  - `PenaltyCircuit` ;
  - `QuestEvaluator` : séries suffisantes, exercice équivalent du même emplacement, mauvais jour.
- **Fonctionnels** :
  - `GameEvents` après synchronisation : XP, plafond de 2 séances par jour, idempotence, notifications ;
  - rétroactivité sans notification ;
  - `QuestScheduler` : création, échec à minuit, plusieurs jours d'absence, pénalité réussie ou ratée, série, pause, séance synchronisée en retard ;
  - affichage des fenêtres système et des notifications stockées.
- **JS (Vitest)** :
  - `count-up.js` ;
  - file de notifications : enchaînement, fermeture, vibration appelée si disponible.
- **Visuel** : pour chaque livraison, une vérification manuelle des écrans sur mobile (360 px) et sur PC, avec et sans réduction des animations. Si l'extension navigateur n'est pas disponible, une simulation jsdom du parcours de séance, comme pour l'étape 1.
