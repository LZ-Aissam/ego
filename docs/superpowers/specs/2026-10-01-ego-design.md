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
- Coût d'hébergement : 0 €.

### Hors périmètre (pour l'instant)

Fil d'activité, commentaires et réactions, nutrition, poids/mensurations/photos, progression automatique des charges, génération par IA, notifications push, unités en livres (lb).

## 2. Décisions validées

| Sujet | Décision |
|---|---|
| Stack | Next.js (App Router, TypeScript) + Tailwind CSS + Supabase (Auth + PostgreSQL + RLS), hébergé sur Vercel |
| Budget | Offres gratuites uniquement |
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

- **Frontend :** Next.js App Router, rendu mobile-first. Sur écran large (≥ 1024 px), les onglets passent dans une barre latérale et le contenu est centré (largeur max. ~720 px).
- **Backend :** Supabase. Aucun serveur applicatif dédié : le code appelle Supabase directement (client ou Server Components), et la sécurité repose sur les règles RLS. Les opérations qui demandent la clé de service (inscription, réinitialisation) passent par des Route Handlers Next.js côté serveur.
- **PWA :** manifest + service worker (icônes, écran de démarrage, installation).
- **Modules isolés (TypeScript pur, testables sans base) :**
  - `lib/auth` : identification prénom + date de naissance (voir §5)
  - `lib/generator` : moteur de génération de programmes (voir §8)
  - `lib/records` : calcul des records et du 1RM estimé
  - `lib/challenges` : calcul de la progression des défis
  - `lib/session-store` : sauvegarde locale de la séance en cours

## 5. Authentification

- **Inscription :** prénom + date de naissance.
- **Connexion :** les mêmes deux champs. La session Supabase est persistante (on reste connecté sur l'appareil).
- **Clé d'identité :** `normaliser(prénom) + date`. La normalisation passe en minuscules, retire les accents et supprime les espaces en début et fin (`« Élise »` → `elise`).
- **Mise en œuvre :** chaque utilisateur est un compte Supabase Auth avec un email technique interne dérivé de la clé d'identité (ex. `elise-2001-04-12@users.ego.local`) et un mot de passe dérivé de cette même clé et d'un secret serveur (`EGO_AUTH_SECRET`), via HMAC. L'inscription et la connexion passent par un Route Handler serveur, pour que le secret ne quitte jamais le serveur.
- **Doublon :** si la clé d'identité existe déjà, l'inscription est refusée avec le message « Ce prénom et cette date de naissance sont déjà utilisés — connecte-toi ».
- **Limite connue et acceptée :** quiconque connaît le prénom et la date de naissance d'un utilisateur peut se connecter à sa place. Tout est isolé dans `lib/auth` et les Route Handlers `/api/auth/*`, pour pouvoir ajouter plus tard un PIN ou un email sans toucher au reste de l'app.
- **Affichage :** le prénom est le nom visible. Chaque profil reçoit une couleur d'avatar pour distinguer deux prénoms identiques.

## 6. Modèle de données (PostgreSQL / Supabase)

Toutes les tables ont `id uuid` (clé primaire) et `created_at timestamptz`.

| Table | Colonnes principales |
|---|---|
| `profiles` | `id` (= auth.users.id), `first_name`, `identity_key` (unique), `birth_date`, `avatar_color`, `sex` (male/female, nullable), `height_cm` (100–250, nullable), `weight_kg` (30–300, nullable), `level` (beginner/intermediate/advanced, nullable), `goal` (strength/hypertrophy/fitness, nullable), `days_per_week` (2–6, nullable), `equipment` (gym/dumbbells/bodyweight, nullable), `active_program_id` (nullable), `is_admin` |
| `exercises` | `name`, `primary_muscles` (text[]), `equipment` (text[] : gym/dumbbells/bodyweight), `kind` (compound/isolation), `slots` (text[] : emplacements qu'il peut occuper), `is_bodyweight` (bool), `start_ratio` (jsonb : ratio charge/poids de corps par sexe et niveau, nullable) |
| `programs` | `owner_id`, `name`, `split` (full_body/upper_lower/ppl_ul/ppl), `params` (jsonb : niveau, objectif, jours, matériel), `copied_from_id` (nullable) |
| `program_days` | `program_id`, `position`, `name` (ex. « Push A ») |
| `program_exercises` | `program_day_id`, `position`, `exercise_id`, `slot`, `sets`, `reps_min`, `reps_max`, `rest_seconds` |
| `workouts` | `user_id`, `program_day_id` (nullable = séance libre), `started_at`, `finished_at`, `client_id` (unique, pour une synchronisation idempotente) |
| `workout_sets` | `workout_id`, `exercise_id`, `position`, `weight_kg` (≥ 0), `reps` (≥ 1), `done_at` |
| `personal_records` | `user_id`, `exercise_id` (unique ensemble), `max_weight_kg`, `max_weight_reps`, `best_e1rm_kg`, `achieved_at` |
| `groups` | `name`, `owner_id`, `invite_code` (unique, 6 caractères) |
| `group_members` | `group_id`, `user_id` (unique ensemble) |
| `challenges` | `creator_id`, `group_id` (nullable = ouvert à tous), `title`, `type` (workout_count/total_volume/max_weight), `exercise_id` (requis si max_weight), `target` (nullable), `starts_on`, `ends_on` |
| `challenge_participants` | `challenge_id`, `user_id` (unique ensemble) |

### Règles RLS

- **Lecture :** tout utilisateur connecté peut lire toutes les tables (l'app est publique entre inscrits).
- **Écriture :** chacun ne modifie que ses propres lignes (`owner_id`, `user_id` ou `creator_id` = `auth.uid()`, ou via le parent dont il est propriétaire). `exercises` est en lecture seule (données initiales chargées par migration). `group_members` : on ne s'ajoute soi-même qu'avec le bon code d'invitation, via une fonction RPC `join_group(code)`.

### Calculs côté base

- **Records :** une fonction `finish_workout(workout_id)` appelée à la fin de la séance recalcule `personal_records` pour les exercices de la séance, et renvoie la liste des nouveaux records.
- **Classement :** une fonction `leaderboard(metric, period, group_id?)` avec
  - `metric` : `volume` (Σ charge × répétitions ; au poids du corps : poids de corps × répétitions + charge additionnelle), `workouts` (nombre de séances terminées), `relative` (moyenne des 3 meilleurs 1RM estimés / poids de corps, exercices poly-articulaires ; les membres sans poids renseigné sont exclus) ou `streak` (semaines consécutives avec au moins une séance, jusqu'à la semaine en cours) ;
  - `period` : `week` (semaine en cours), `month` (mois en cours) ou `all` ;
  - `streak` ignore `period`.
- **Défis :** une vue ou fonction `challenge_progress(challenge_id)` calcule pour chaque participant la valeur atteinte entre `starts_on` et `ends_on`.

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

## 8. Générateur de programmes (`lib/generator`)

Fonction pure : `generateProgram(params, exercises) → Program`.

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

**6. Remplacement :** `alternativesFor(slot, equipment, exercises)` renvoie les autres exercices qui peuvent remplir le même emplacement.

**Bibliothèque initiale :** environ 60 exercices en français, chargés par une migration SQL (seed). Chaque emplacement a au moins un exercice pour chaque type de matériel, quand ça a du sens (poids du corps compris).

## 9. Séance en cours et hors ligne

- L'état de la séance (exercices, séries validées, minuteur) est conservé dans `localStorage` par `lib/session-store`, à chaque série validée.
- Au lancement de l'app, si une séance non terminée existe, l'app propose « Reprendre la séance ».
- « Terminer » envoie la séance (avec son `client_id` généré localement) puis appelle `finish_workout`. En cas d'échec réseau, la séance reste en attente et l'envoi est retenté au prochain lancement ou au retour du réseau. `client_id` garantit qu'une séance n'est jamais enregistrée deux fois.

## 10. Gestion des erreurs

- **Formulaires :** validation dans le formulaire (Zod), avec des messages en français (champ requis, date de naissance invalide ou dans le futur, taille hors 100–250 cm, poids hors 30–300 kg, charge < 0, répétitions < 1).
- **Réseau :** un bandeau non bloquant « Hors ligne — tes données seront synchronisées ». Les actions qui nécessitent le réseau (générer, rejoindre un groupe…) sont désactivées hors ligne, avec une explication.
- **Erreurs serveur :** un message générique affiché dans un toast, avec le détail envoyé dans la console.

## 11. Tests

- **Unitaires (Vitest) :**
  - `lib/generator` : découpage par nombre de jours, respect du matériel, nombre d'exercices selon le niveau, séries et répétitions selon l'objectif, pas de doublon dans un même jour
  - `lib/records` : 1RM (Epley : `charge × (1 + reps / 30)`), ratio × poids de corps, volume des exercices au poids du corps
  - `lib/generator` : charges de départ suggérées selon poids, sexe et niveau (arrondi à 2,5 kg, rien pour le poids du corps)
  - `lib/auth` : normalisation du prénom et de la clé d'identité
  - `lib/challenges` : progression selon le type de défi
  - `lib/session-store` : reprise d'une séance
- **Bout en bout (Playwright, viewport mobile) :** inscription → génération d'un programme → séance complète → le record apparaît sur l'accueil.
- **Base :** les fonctions SQL (`finish_workout`, `leaderboard`, `challenge_progress`) sont testées sur une base Supabase locale (`supabase start`).
