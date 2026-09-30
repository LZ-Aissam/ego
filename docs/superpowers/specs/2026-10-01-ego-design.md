# EGO — Spécification de conception

**Date :** 2026-10-01
**Statut :** en relecture

## 1. Objectif

EGO est une application web de musculation, conçue d'abord pour le mobile (installable en PWA) et utilisable sur PC. Elle s'adresse pour l'instant à un groupe d'amis, mais tous les inscrits se voient entre eux (profils publics). Elle permet de :

- **générer automatiquement un programme** d'entraînement selon son profil ;
- **suivre une séance guidée** (exercice suivant, minuteur de repos, charges de la dernière fois) ;
- **tenir un carnet** (historique, progression, records) ;
- **se comparer entre amis** (records visibles, classement, défis, groupes, partage de programmes).

### Critères de réussite

- Un nouvel utilisateur s'inscrit, génère un programme et termine sa première séance guidée en moins de 5 minutes, depuis son téléphone.
- Une séance en cours n'est jamais perdue (fermeture de l'app, perte de réseau).
- Le classement et les défis se mettent à jour sans action manuelle.
- Coût d'hébergement : environ 4–6 €/mois (petit VPS). L'app reste compatible avec un hébergement mutualisé sans SSH (InfinityFree) pour un essai : aucun worker de file d'attente ni cron n'est obligatoire.

### Hors périmètre (pour l'instant)

Fil d'activité, commentaires et réactions, nutrition, poids/mensurations/photos, progression automatique des charges, génération par IA, notifications push, unités en livres (lb).

## 2. Décisions validées

| Sujet | Décision |
|---|---|
| Stack | Laravel 12 (PHP 8.4) + Livewire 3 + Alpine.js + Tailwind CSS 4 (Vite) ; MySQL en production, SQLite en local et pour les tests |
| Budget | Quelques euros par mois (VPS) ; essai possible sur InfinityFree |
| Langue / unités | Interface en français, charges en kg |
| Connexion | Prénom + date de naissance (choix assumé, remplaçable plus tard) |
| Visibilité | Tout est public entre inscrits ; groupes facultatifs |
| Programmes | Générés par un moteur de règles (pas d'IA) |
| Coach | Guidage pendant la séance uniquement |
| Social | Records, classement, défis, groupes, partage de programmes (pas de fil d'activité) |

## 3. Découpage en étapes

Chaque étape donne une version utilisable.

1. **Base + carnet + coach :** comptes, profil, bibliothèque d'exercices, séance libre guidée, historique, progression, records.
2. **Générateur de programmes :** questionnaire, génération, programme actif, séances du programme en mode guidé, remplacement d'exercice.
3. **Social :** liste des membres et profils publics, classement, défis, groupes, copie de programmes.

## 4. Architecture

- **Application Laravel monolithique**, rendue côté serveur. Les pages sont des composants **Livewire** ; l'interactivité locale (minuteur, boutons +/−) est en **Alpine.js** (fourni avec Livewire).
- **Mise en page mobile-first** (Tailwind). Sur écran large (≥ 1024 px), les onglets passent dans une barre latérale et le contenu est centré (largeur max. ~720 px).
- **Écran « Séance en cours » :** composant Alpine autonome (`resources/js/workout/`). Il garde l'état dans `localStorage` et envoie la séance terminée à une route JSON (`POST /workouts/sync`, authentifiée par la session et protégée par CSRF). Il ne fait aucun aller-retour serveur pendant la séance, donc il fonctionne hors ligne.
- **PWA :** `public/manifest.json` + `public/sw.js` (mise en cache de l'écran de séance et des assets compilés ; stratégie « réseau d'abord » pour les pages).
- **Assets :** compilés en local avec Vite (`npm run build`) puis déployés dans `public/build`. Le serveur n'a pas besoin de Node.
- **Autorisation :** Policies Laravel. Toute page nécessite d'être connecté. Tout utilisateur connecté peut **lire** les données des autres ; chacun ne **modifie** que ce qui lui appartient.
- **Services métier isolés (PHP pur, testables sans HTTP) :**
  - `App\Support\IdentityKey` : normalisation prénom + date (voir §5)
  - `App\Services\ProgramGenerator` : génération de programmes (voir §8)
  - `App\Services\StartingLoad` : charges de départ suggérées
  - `App\Services\RecordCalculator` : 1RM estimé, volume, ratio au poids de corps
  - `App\Services\WorkoutSync` : enregistrement idempotent d'une séance + mise à jour des records
  - `App\Services\Leaderboard` et `App\Services\ChallengeProgress` : classement et défis

## 5. Authentification

- **Inscription :** prénom + date de naissance.
- **Connexion :** les mêmes deux champs. `Auth::login($user, remember: true)` pose un cookie « se souvenir de moi » : on reste connecté sur l'appareil.
- **Clé d'identité :** `normaliser(prénom) + date`. La normalisation passe en minuscules, retire les accents et supprime les espaces en début et fin (`« Élise »` → `elise`).
- **Mise en œuvre :** la table `users` n'a ni email ni mot de passe ; elle a une colonne `identity_key` (unique). La connexion cherche l'utilisateur par `identity_key` et ouvre la session. Les tentatives de connexion sont limitées (`RateLimiter` : 5 essais par minute et par IP), pour qu'on ne puisse pas essayer des dates de naissance à la chaîne.
- **Doublon :** si la clé d'identité existe déjà, l'inscription est refusée avec le message « Ce prénom et cette date de naissance sont déjà utilisés — connecte-toi ».
- **Limite connue et acceptée :** quiconque connaît le prénom et la date de naissance d'un utilisateur peut se connecter à sa place. Tout est isolé dans `IdentityKey` et les composants Livewire `Auth\Register` / `Auth\Login`, pour pouvoir ajouter plus tard un PIN ou un email sans toucher au reste de l'app.
- **Affichage :** le prénom est le nom visible. Chaque profil reçoit une couleur d'avatar pour distinguer deux prénoms identiques.

## 6. Modèle de données (migrations Laravel, MySQL / SQLite)

Toutes les tables ont `id` (bigint auto-incrémenté) et `created_at` / `updated_at`. Les colonnes « liste » ou « objet » sont en `json` (cast `array` côté Eloquent).

| Table | Colonnes principales |
|---|---|
| `users` | `first_name`, `identity_key` (unique), `birth_date`, `avatar_color`, `sex` (male/female, nullable), `height_cm` (100–250, nullable), `weight_kg` (decimal 5,1 ; 30–300, nullable), `level` (beginner/intermediate/advanced, nullable), `goal` (strength/hypertrophy/fitness, nullable), `days_per_week` (2–6, nullable), `equipment` (gym/dumbbells/bodyweight, nullable), `active_program_id` (nullable), `is_admin` (bool), `remember_token` |
| `exercises` | `slug` (unique), `name`, `primary_muscles` (json), `equipment` (json : gym/dumbbells/bodyweight), `kind` (compound/isolation), `slots` (json : emplacements qu'il peut occuper), `is_bodyweight` (bool), `start_ratio` (json : ratio charge/poids de corps par sexe et niveau, nullable) |
| `programs` | `user_id`, `name`, `split` (full_body/upper_lower/ppl_ul/ppl), `params` (json : niveau, objectif, jours, matériel), `copied_from_id` (nullable) |
| `program_days` | `program_id`, `position`, `name` (ex. « Push A ») |
| `program_exercises` | `program_day_id`, `position`, `exercise_id`, `slot`, `sets`, `reps_min`, `reps_max`, `rest_seconds` |
| `workouts` | `user_id`, `program_day_id` (nullable = séance libre), `client_id` (uuid, unique : synchronisation idempotente), `started_at`, `finished_at`, `bodyweight_kg` (poids de l'utilisateur au moment de la séance, nullable) |
| `workout_sets` | `workout_id`, `exercise_id`, `position`, `weight_kg` (decimal 6,2 ; ≥ 0 ; pour un exercice au poids du corps, c'est la charge additionnelle), `reps` (≥ 1), `done_at` |
| `personal_records` | `user_id`, `exercise_id` (unique ensemble), `max_weight_kg`, `max_weight_reps`, `best_e1rm_kg`, `achieved_at` |
| `groups` | `name`, `user_id` (créateur), `invite_code` (unique, 6 caractères) |
| `group_user` | `group_id`, `user_id` (unique ensemble) |
| `challenges` | `user_id` (créateur), `group_id` (nullable = ouvert à tous), `title`, `type` (workout_count/total_volume/max_weight), `exercise_id` (requis si max_weight), `target` (nullable), `starts_on`, `ends_on` |
| `challenge_user` | `challenge_id`, `user_id` (unique ensemble) |

### Calculs (en PHP, requêtes Eloquent / Query Builder compatibles MySQL et SQLite)

- **Records :** `WorkoutSync::sync(User $user, array $payload)` enregistre la séance dans une transaction, puis met à jour `personal_records` pour les exercices de la séance. Elle renvoie la séance et la liste des nouveaux records. Si le `client_id` existe déjà, elle renvoie la séance existante sans rien dupliquer.
- **Classement :** `Leaderboard::rank(string $metric, string $period, ?Group $group)` avec
  - `metric` : `volume` (Σ charge × répétitions ; au poids du corps : (poids de corps de la séance + charge additionnelle) × répétitions), `workouts` (nombre de séances terminées), `relative` (moyenne des 3 meilleurs 1RM estimés / poids de corps actuel, exercices poly-articulaires ; les membres sans poids renseigné sont exclus) ou `streak` (semaines consécutives avec au moins une séance, jusqu'à la semaine en cours) ;
  - `period` : `week` (semaine en cours, du lundi au dimanche), `month` (mois en cours) ou `all` ;
  - `streak` et `relative` ignorent `period`.
- **Défis :** `ChallengeProgress::for(Challenge $challenge)` calcule pour chaque participant la valeur atteinte entre `starts_on` et `ends_on`.

## 7. Écrans

Navigation : une barre d'onglets en bas sur mobile, une barre latérale sur PC. Quatre onglets.

1. **Accueil**
   - La séance du jour du programme actif (le jour qui suit la dernière séance réalisée du programme), avec un bouton « Commencer »
   - Un bouton « Séance libre »
   - Les 3 derniers records
   - Les défis en cours
2. **Programme**
   - Le programme actif (jours et exercices)
   - « Générer un programme » : questionnaire en 5 étapes (morphologie, niveau, objectif, jours, matériel)
   - Remplacer un exercice
   - Les programmes des autres membres, avec un bouton « Copier » (étape 3)
3. **Communauté** (étape 3)
   - Classement : métrique × période × groupe
   - Défis : en cours, à rejoindre, créer
   - Groupes : mes groupes, créer, rejoindre avec un code
   - Membres : liste, profil public (records, programme actif)
4. **Profil**
   - Historique des séances
   - Progression par exercice (graphique de la charge max. par séance)
   - Records (avec ratio × poids de corps)
  - Morphologie : sexe, taille, poids (modifiables), IMC indicatif
   - Déconnexion

**Écran « Séance en cours »** (plein écran, sans barre d'onglets)
- Exercice actuel en grand ; séries prévues ; rappel « Dernière fois : 3×8 à 60 kg »
- Saisie de la charge et des répétitions avec des boutons +/− (pas de 2,5 kg et de 1 répétition), pré-remplie avec la dernière valeur, ou à défaut avec la charge de départ suggérée (§8), puis « Valider la série »
- Le minuteur de repos démarre automatiquement après chaque série, avec vibration (`navigator.vibrate`) et signal sonore à la fin ; on peut le passer ou ajouter 30 s
- « Exercice suivant » / « Ajouter un exercice » (séance libre) ; à la fin, « Terminer » affiche un résumé (durée, volume, nouveaux records)

**Écrans d'entrée :** Inscription et Connexion, chacun avec deux champs (prénom, date de naissance).

## 8. Générateur de programmes (`App\Services\ProgramGenerator`)

Service sans accès à la base : `generate(GeneratorParams $params, Collection $exercises): GeneratedProgram` (objets de valeur). C'est l'appelant qui enregistre le résultat.

**Entrées :** `sex`, `heightCm`, `weightKg`, `level`, `goal`, `daysPerWeek` (2–6), `equipment`.

Le questionnaire a 5 étapes : **(1) morphologie** (sexe, taille, poids — pré-remplis depuis le profil s'ils sont connus, puis enregistrés dans `profiles`), (2) niveau, (3) objectif, (4) jours par semaine, (5) matériel.

**Usage de la morphologie :**
- **Charges de départ suggérées :** pour la première séance d'un exercice (quand il n'y a pas encore d'historique), l'app pré-remplit une charge = `poids de corps × ratio(exercice, sexe, niveau)`, arrondie à 2,5 kg. Les ratios sont stockés par exercice dans la bibliothèque (`start_ratio` jsonb, ex. développé couché homme débutant 0,5 ; femme débutante 0,3). Pour les exercices au poids du corps, on ne suggère pas de charge.
- **Volume au poids du corps :** pour un exercice au poids du corps, le volume d'une série vaut `poids de corps × répétitions` (+ charge additionnelle éventuelle).
- **Force relative :** les records affichent aussi le ratio `charge max / poids de corps` (ex. « 1,2 × PDC »), et le classement propose la métrique `relative` (meilleur 1RM estimé / poids de corps, tous exercices poly-articulaires confondus, moyenne des 3 meilleurs).
- **Taille :** enregistrée dans le profil et affichée, avec l'IMC calculé à titre indicatif. Elle n'influence pas le programme.
- Le sexe ne change pas le découpage ni les séries et répétitions : seules les charges suggérées en dépendent.

**1. Découpage**

| Jours | Découpage | Jours générés |
|---|---|---|
| 2 | full_body | Full body A, Full body B |
| 3 | full_body | Full body A, B, C |
| 4 | upper_lower | Haut A, Bas A, Haut B, Bas B |
| 5 | ppl_ul | Push, Pull, Legs, Haut, Bas |
| 6 | ppl | Push A, Pull A, Legs A, Push B, Pull B, Legs B |

**2. Emplacements par type de jour.** Chaque type de jour (full body, haut, bas, push, pull, legs) a une liste ordonnée d'emplacements. Les emplacements poly-articulaires viennent d'abord. Exemples d'emplacements : `horizontal_push`, `vertical_push`, `horizontal_pull`, `vertical_pull`, `squat`, `hinge`, `lunge`, `chest_iso`, `shoulder_iso`, `biceps`, `triceps`, `quads_iso`, `hamstrings_iso`, `calves`, `core`.

**3. Nombre d'emplacements utilisés selon le niveau :** débutant 4–5, intermédiaire 5–6, avancé 6–7. On garde les N premiers de la liste.

**4. Choix des exercices.** Pour chaque emplacement, on prend les exercices compatibles avec le matériel. On choisit de façon déterministe (tri stable + rotation selon l'indice du jour), pour que les variantes A et B d'un même type de jour utilisent des exercices différents quand c'est possible. Si aucun exercice n'est compatible, l'emplacement est ignoré.

**5. Séries, répétitions, repos**

| Objectif | Poly-articulaire | Isolation |
|---|---|---|
| strength | 5 × 3–5, 180 s (débutant : 4 séries) | 3 × 8–10, 90 s |
| hypertrophy | 4 × 6–10, 120 s (débutant : 3 séries) | 3 × 10–15, 60 s |
| fitness | 3 × 10–12, 90 s | 3 × 12–15, 60 s (débutant : 2 séries) |

**6. Remplacement :** `alternativesFor(string $slot, string $equipment, Collection $exercises)` renvoie les autres exercices qui peuvent remplir le même emplacement.

**Bibliothèque initiale :** environ 60 exercices en français, chargés par un seeder (`ExerciseSeeder`, idempotent grâce au `slug`). Chaque emplacement a au moins un exercice pour chaque type de matériel, quand ça a du sens (poids du corps compris).

## 9. Séance en cours et hors ligne

- **Démarrage :** la page `/workout` (ou `/workout/day/{programDay}`) fournit au composant Alpine, en JSON intégré à la page, les exercices prévus, la « dernière fois » par exercice, les charges de départ suggérées et la bibliothèque d'exercices (pour « Ajouter un exercice »).
- **Pendant :** l'état (exercices, séries validées, minuteur) est écrit dans `localStorage` (clé `ego.activeWorkout`) à chaque action, par `resources/js/workout/store.js`. Aucun appel réseau.
- **Reprise :** au chargement de n'importe quelle page, si `ego.activeWorkout` existe, un bandeau propose « Reprendre la séance ».
- **Fin :** « Terminer » envoie `POST /workouts/sync` avec le `client_id` (uuid généré au démarrage). En cas d'échec réseau, la séance passe dans `ego.pendingWorkouts`, et l'envoi est retenté au chargement des pages suivantes et à l'événement `online`. Le `client_id` garantit qu'une séance n'est jamais enregistrée deux fois.
- **Session expirée (419/401) :** la séance reste en attente ; elle est envoyée automatiquement après la reconnexion.

## 10. Gestion des erreurs

- **Formulaires :** validation Laravel (règles dans les composants Livewire), avec des messages en français (`lang/fr`) : champ requis, date de naissance invalide ou dans le futur, taille hors 100–250 cm, poids hors 30–300 kg, charge < 0, répétitions < 1.
- **Synchronisation :** `POST /workouts/sync` valide le contenu (exercices existants, charges et répétitions dans les bornes, `client_id` au format uuid) et répond 422 avec le détail. La séance reste alors en attente, et un message l'indique.
- **Réseau :** un bandeau non bloquant « Hors ligne — ta séance sera synchronisée ». Les actions qui nécessitent le réseau sont désactivées hors ligne, avec une explication.
- **Erreurs serveur :** pages d'erreur Laravel personnalisées en français ; les erreurs sont journalisées (`storage/logs`).

## 11. Tests

- **Unitaires (Pest) :**
  - `ProgramGenerator` : découpage selon le nombre de jours, respect du matériel, nombre d'exercices selon le niveau, séries et répétitions selon l'objectif, pas de doublon dans un même jour
  - `StartingLoad` : charges de départ selon le poids, le sexe et le niveau (arrondi à 2,5 kg, rien pour le poids du corps)
  - `RecordCalculator` : 1RM (Epley : `charge × (1 + reps / 30)`, et `charge` si reps = 1), ratio × poids de corps, volume des exercices au poids du corps
  - `IdentityKey` : normalisation du prénom et de la clé d'identité
- **Fonctionnels (Pest + SQLite en mémoire) :** inscription (doublon compris), connexion et limitation des tentatives, `WorkoutSync` (idempotence, records), `Leaderboard`, `ChallengeProgress`, Policies (impossible de modifier les données d'un autre).
- **JavaScript (Vitest) :** `resources/js/workout/store.js` (reprise, file d'attente, nouvelle tentative).
