# EGO — Charges : calibrage et charge prescrite — Spécification

**Date :** 2026-10-04
**Statut :** en relecture
**Complète :** `2026-10-01-ego-systeme-design.md`. **Revient sur une décision de la spec de base** : la « progression automatique des charges », jusque-là hors périmètre, est maintenant voulue.

## 1. Intention

TON EGO doit savoir **combien le joueur peut porter** (sa force réelle, exercice par exercice) et lui dire **combien il doit porter** à chaque série, puis faire évoluer cette charge au fil de ses progrès.

### Critères de réussite

- Après un calibrage, chaque exercice du programme a une **charge prescrite**, affichée dans la quête et pré-remplie dans la séance.
- La charge augmente quand le joueur réussit le haut de sa plage, et baisse après des échecs répétés. Chaque changement est annoncé par TON EGO.
- Le calibrage est sûr : on ne fait jamais un vrai test de 1RM, seulement un test de répétitions max.
- Rien ne se perd hors ligne. La séance de calibrage, elle, demande le réseau, puisqu'elle passe par une page Livewire.

### Hors périmètre

- Vrai test de 1RM.
- Mise à jour automatique du 1RM à chaque séance (seuls le calibrage et la progression font évoluer les charges).
- Progression en répétitions pour les exercices au poids du corps (au-delà d'une suggestion de lest).
- Charges en livres.
- Disques et barres spécifiques (on arrondit simplement à 2,5 kg).

## 2. Décisions validées

| Sujet | Décision |
|---|---|
| Connaître la force | **Séance de calibrage** guidée |
| Protocole | **Test de répétitions max** : 2 séries d'échauffement guidées, puis 1 série au maximum de répétitions avec une charge choisie (idéalement 5 à 10 répétitions) ; 1RM estimé par Epley |
| Charge prescrite | Départ en **pourcentage du 1RM**, puis **double progression** |
| Exercices non testés | Estimation existante (poids, sexe, niveau) × **facteur de puissance** mesuré |
| Quand calibrer | Première quête d'entraînement après le programme = quête **CALIBRAGE** ; relançable depuis la page Programme |

## 3. Calibrage

### 3.1 Mouvements testés

Ils sont pris **dans le programme actif**, en suivant cet ordre de priorité d'emplacements. Pour chaque emplacement, on retient le premier exercice du programme qui l'occupe (dans l'ordre des jours puis des positions) ; à défaut, le premier candidat du générateur pour le matériel du joueur.

1. `horizontal_push`
2. `horizontal_pull`, sinon `vertical_pull`
3. `squat`, sinon `lunge`
4. `hinge`, uniquement pour les niveaux `intermediate` et `advanced` avec le matériel `gym` ou `dumbbells`

On obtient donc 3 ou 4 mouvements. Un emplacement sans exercice est ignoré.

### 3.2 Déroulé pour chaque mouvement

- **Charge estimée** `E` = `StartingLoad::suggest(exercice, joueur)`, ou 20 kg par défaut si l'exercice est chargé et qu'il n'y a pas de suggestion. Pour un exercice au poids du corps, il n'y a pas de charge.
- **Échauffement 1** : `arrondi2,5(E × 0,5)` × 8 répétitions. **Échauffement 2** : `arrondi2,5(E × 0,7)` × 4. Ce sont des propositions affichées ; le joueur les valide en un appui, ou les ajuste.
- **Série test** : le joueur saisit la charge (pré-remplie à `E`) et le **nombre de répétitions réalisées au maximum** (de 1 à 30).
- **1RM estimé** = `RecordCalculator::estimatedOneRepMax(charge effective, reps)`, la charge effective incluant le poids de corps pour un exercice au poids du corps.
- Au-delà de 12 répétitions, TON EGO affiche « Mesure moins précise : la prochaine fois, prends plus lourd », et garde la valeur.

### 3.3 Fin du calibrage

- Le calibrage est enregistré comme **une séance ordinaire** (`WorkoutSync`), avec les échauffements et la série test de chaque mouvement. Elle rapporte donc l'XP et les records habituels, et peut accomplir la quête CALIBRAGE (voir §3.4).
- **Profil de force** : `exercise_maxes` est créé ou mis à jour pour chaque mouvement testé (`source = calibration`).
- **Facteur de puissance** `F` = moyenne, sur les mouvements testés et chargés, de `charge de départ prescrite / E`, bornée entre 0,5 et 2. Il est enregistré sur l'utilisateur (`users.power_factor`) ; s'il n'y a aucun mouvement chargé, il vaut 1.
- **Charges prescrites** (§4.1) : calculées pour **tous** les exercices chargés du programme actif.
- `users.calibrated_at` = maintenant.
- **Notifications** : « PUISSANCE MESURÉE » avec une ligne par mouvement (`Développé couché : 1RM ≈ 82,5 kg`), puis les notifications de jeu habituelles (XP, quête, niveau).

### 3.4 Quête CALIBRAGE

- Tant que `users.calibrated_at` est vide, la **première quête d'entraînement créée** est de type `calibration` au lieu de `training` :
  - titre « Calibrage » ;
  - objectifs : les mouvements testés, avec **3 séries** chacun (2 d'échauffement et 1 test) ;
  - même jour de programme que la quête d'entraînement qu'elle remplace, sans faire avancer la rotation des jours de programme (le jour suivant reste le premier jour).
- Elle s'accomplit, rapporte et échoue (avec sa pénalité) **exactement comme une quête d'entraînement** : mêmes règles d'XP, de série de jours et de pénalité. Pour toutes les règles existantes, `calibration` est traité comme `training`.
- « Accepter la quête » ouvre l'**écran de calibrage** (et non l'écran de séance).
- **Relancer un calibrage** depuis la page Programme ouvre le même écran sans quête associée. Les charges prescrites sont recalculées, et l'historique de progression des exercices testés est remis à zéro.

## 4. Charge prescrite

### 4.1 Charge de départ

- **Exercice testé** (chargé) : `départ = arrondi2,5_inférieur(1RM / (1 + (reps_max + 2) / 30))`, avec un minimum de 2,5 kg. Ici, `reps_max` est le haut de la plage de répétitions de cet exercice dans le programme ; s'il y figure plusieurs fois, on prend la plus petite valeur.
- **Exercice non testé** (chargé) : `départ = arrondi2,5(StartingLoad::suggest(ex, joueur) × F)`, avec un minimum de 2,5 kg. Sans suggestion possible (sexe ou poids inconnu), il n'y a pas de charge prescrite.
- **Exercice au poids du corps** : pas de charge prescrite. La quête affiche seulement les répétitions.
- Enregistrement : `working_loads(user_id, exercise_id, target_kg, fail_streak = 0)`.

### 4.2 Double progression

Elle s'applique **après chaque séance synchronisée** (créée), pour chaque exercice de la séance qui a une charge prescrite et qui figure dans le programme actif (avec ses séries et sa plage de répétitions).

On ne retient que les **séries faites à au moins la charge prescrite**. Soit `n` leur nombre et `S` le nombre de séries prévues.

| Cas | Condition | Effet |
|---|---|---|
| **Réussite** | `n ≥ S` et toutes ces séries ont `reps ≥ reps_max` | `target += 5 kg` si l'emplacement est `squat`, `hinge`, `lunge`, `quads_iso`, `hamstrings_iso` ou `calves`, sinon `+2,5 kg` ; `fail_streak = 0` ; notification « CHARGE AUGMENTÉE » |
| **Échec** | une des séries retenues a `reps < reps_min` | `fail_streak += 1` ; à 2 : `target = max(2,5, arrondi2,5(target × 0,9))`, `fail_streak = 0`, notification « DÉCHARGE » |
| **Consolidation** | sinon, avec au moins une série retenue | `fail_streak = 0` |
| **Allègement volontaire** | aucune série retenue | rien ne change |

- Un exercice présent plusieurs fois dans la séance est évalué **une seule fois**.
- Les notifications de progression viennent **après** celles de quête et **avant** celles de niveau.
- **Exercice au poids du corps** : si `n ≥ S` et que toutes les séries ont `reps ≥ reps_max + 5`, une notification « LESTE-TOI » est affichée (« Ajoute 2,5 kg de lest la prochaine fois »), sans enregistrement.

### 4.3 Affichage

- **Quête du jour** : `Développé couché · 3 × 6–10 · 70 kg`. Sans charge prescrite, rien n'est ajouté.
- **Écran de séance** (séance de quête et séance libre) : le brouillon de charge est **pré-rempli avec la charge prescrite** quand elle existe, sinon on garde le comportement actuel (dernière fois, sinon suggestion). Une ligne « Charge prescrite par TON EGO · 70 kg » est ajoutée ; « Dernière fois · … » reste affiché.
- **Page Programme** : chaque exercice affiche sa charge actuelle. Un bouton « Recalibrer » est ajouté.
- **Fiche du joueur** : une fenêtre **PROFIL DE FORCE** liste les 1RM mesurés, avec leur date.

## 5. Données

| Table / colonnes | Rôle |
|---|---|
| `exercise_maxes` : `user_id`, `exercise_id`, `e1rm_kg` (decimal 6,1), `source` (`calibration`), `measured_at` ; unique `(user_id, exercise_id)` | profil de force |
| `working_loads` : `user_id`, `exercise_id`, `target_kg` (decimal 6,2), `fail_streak` (tinyint), `updated_at` ; unique `(user_id, exercise_id)` | charge prescrite |
| `users` + `calibrated_at` (timestamp nullable), `power_factor` (decimal 4,2 nullable) | état du calibrage |
| `quests.type` accepte `calibration` | quête de calibrage |

## 6. Services

| Service | Responsabilité |
|---|---|
| `App\Game\Calibration` | `movementsFor(User): list<Exercise>` ; `plan(User): list<{exercise, estimate, warmups}>` ; `complete(User, array $results, ?Quest): array{workout, notifications}` (enregistre la séance, le profil de force, le facteur et les charges prescrites) |
| `App\Game\LoadPrescriber` | `startingLoad(float $e1rm, int $repsMax): float` ; `derivedLoad(Exercise, User): ?float` ; `prescribeProgram(User): void` ; `progress(User, Workout): list<notification>` |
| `App\Game\GameEvents` | appelle `LoadPrescriber::progress` après les quêtes |
| `App\Game\QuestScheduler` | crée une quête `calibration` à la place de la première `training` tant que le joueur n'est pas calibré ; traite `calibration` comme `training` partout |

Interface : un composant Livewire `CalibrationPage`, avec la route `GET /calibration` (et `GET /calibration/quest/{quest}`), un mouvement à la fois, des échauffements validés en un appui, puis la série test.

## 7. Cas limites

- **Programme régénéré** : les charges prescrites des exercices déjà connus sont conservées ; les nouveaux exercices reçoivent une charge déduite (`F`). Le profil de force est conservé.
- **Joueur sans poids ou sans sexe** : le calibrage reste possible, puisque le joueur saisit la charge de sa série test ; seule l'estimation `E` vaut 20 kg par défaut. Les exercices non testés n'ont pas de charge prescrite.
- **Série test à 0 répétition ou charge vide** : refusée, avec le message « Indique la charge et le nombre de répétitions réalisées ».
- **Calibrage abandonné en cours** : rien n'est enregistré. La quête reste en attente, puis échoue comme une quête normale à minuit.
- **Charge prescrite inférieure à 2,5 kg** : bornée à 2,5 kg.
- **Exercice remplacé dans le programme** : le nouvel exercice reçoit une charge déduite s'il n'en a pas déjà une.

## 8. Tests

- **Unitaires** :
  - `LoadPrescriber::startingLoad` (100 kg et 5 reps → 80 ; 100 kg et 10 reps → 70 ; arrondi inférieur ; minimum 2,5) ;
  - `derivedLoad` (facteur appliqué et borné) ;
  - progression : réussite (+2,5 ou +5 selon l'emplacement), échec simple, décharge au 2e échec, consolidation, allègement volontaire, exercice présent deux fois, lest suggéré.
- **Fonctionnels** :
  - mouvements testés selon le programme, le matériel et le niveau ;
  - `complete()` : séance enregistrée, profil de force, facteur, charges prescrites pour tout le programme, notifications ;
  - quête `calibration` créée à la place de la première `training`, accomplie par la séance de calibrage, sans faire avancer la rotation ;
  - recalibrage ;
  - affichage de la charge dans la quête, la séance (pré-remplissage), le programme et la fiche ;
  - progression déclenchée après une synchronisation de séance.
- **JS** : pré-remplissage de la charge prescrite dans `store.js` (`target.kg`).
