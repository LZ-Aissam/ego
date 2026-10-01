# EGO — Livraison F « Force × Vitesse »

## 1. Objectif et périmètre

Permettre à un programme de viser la **puissance** (force × vitesse) et pas seulement la force classique : chaque exercice d'un programme reçoit une **intention** (vitesse, force max, explosif, masse) qui change la charge prescrite, la consigne affichée et la progression. Puis remplacer le programme d'Aïssam par un programme « force × vitesse » : quatre jours haut du corps et un vendredi jambes et hanches explosives.

**Ce que l'utilisateur a demandé (ses mots)** : force pure « digne d'un monstre », beaucoup de système nerveux, masse musculaire pour la force maximale, corps sec, explosivité, vitesse de production de force et « le Graal : Force × Vitesse » ; haut du corps (pecs, dos, épaules, biceps, triceps, avant-bras, pompes diamant, abdos) et le vendredi uniquement les jambes, pour les gestes explosifs des hanches et des jambes.

**Hors périmètre** : alimentation (une ligne de conseil seulement), mesure de la vitesse réelle de la barre (capteur), intentions dans le générateur automatique de programmes (les programmes générés gardent leur comportement actuel), éditeur de programme dans l'interface.

## 2. Intentions

| Intention | Icône · libellé | Charge prescrite | Consigne affichée | Progression |
|---|---|---|---|---|
| `speed` | ⚡ VITESSE | 57,5 % du meilleur 1RM estimé, arrondi à 2,5 kg | « Charge légère : chaque répétition le plus vite possible, arrête la série dès que ça ralentit. » | Aucune hausse par les répétitions : la charge suit le 1RM (records, calibrage). |
| `max` | 🔥 FORCE MAX | 87,5 % du meilleur 1RM estimé, arrondi à 2,5 kg (plancher) | « Lourd : 1 à 3 répétitions parfaites, repos complet. » | Double progression actuelle (+2,5 kg haut du corps, +5 kg bas du corps) quand toutes les séries atteignent le haut de la plage ; décharge après 2 échecs. |
| `explosive` | 💥 EXPLOSIF | Poids du corps ; pour un exercice chargé, 40 % du 1RM estimé | « Explosion maximale, repos complet entre les séries : la qualité avant la fatigue. » | Aucune ; pas de suggestion « LESTE-TOI ». |
| `mass` | 🧱 MASSE | Calcul actuel (charge de départ selon la plage de répétitions) | « Contrôle la descente, tension continue. » | Double progression actuelle. |
| `null` | — | Calcul actuel | — | Comportement actuel (programmes générés). |

- **Meilleur 1RM estimé** d'un exercice : le plus grand de son 1RM calibré (`exercise_maxes`) et de son record (`personal_records.best_e1rm_kg`). Pour un exercice sans 1RM connu, on part de sa charge déduite actuelle (rapportée à un 1RM par la formule d'Epley sur `reps_max`).
- La charge **vitesse** et la charge **explosif** sont recalculées à chaque prescription et après chaque séance, à partir du meilleur 1RM : elles montent quand la force monte.

## 3. Données

- `program_exercises.intent` : string(10), nullable (`speed`, `max`, `explosive`, `mass`).
- **Nouveaux exercices** (catalogue, fiche « Comment faire ? », animation, ratio de départ) :

| Slug | Nom | Matériel | Type | Emplacement | Ratio débutant homme |
|---|---|---|---|---|---|
| `push-press` | Développé poussé (barre) | gym | compound | vertical_push | 0,4 |
| `clap-push-up` | Pompes claquées | poids du corps, haltères, gym | compound | horizontal_push | — |
| `box-jump` | Sauts sur box | poids du corps, haltères, gym | compound | jump | — |
| `broad-jump` | Saut en longueur | poids du corps, haltères, gym | compound | jump | — |
| `jump-squat` | Squat sauté | poids du corps, haltères, gym | compound | jump | — |

- Nouvel emplacement `jump`, compté comme **bas du corps** (progression et déduction des charges) ; il n'entre pas dans les journées du générateur automatique.

## 4. Affichage

- **Quête du jour (accueil)** : chaque objectif montre son icône d'intention (« ⚡ Développé couché · 8 × 3 · 52,5 kg »).
- **Séance** : sous le nom de l'exercice, une ligne « ⚡ VITESSE · charge prescrite 52,5 kg » et la consigne de l'intention.
- **Page Programme** : icône et libellé de l'intention sur chaque ligne ; une ligne de conseil en tête des programmes qui contiennent des intentions : « Corps sec : vise 1,8 à 2 g de protéines par kg et un léger déficit si tu veux sécher. »
- **Calibrage** : inchangé (il teste les mouvements présents dans le programme).

## 5. Programme d'Aïssam (remplacé à la fin de la livraison)

Objectif force, niveau avancé, salle complète, lundi → vendredi. Ordre de chaque séance : explosif → force max ou vitesse → masse → abdos.

| Jour | Exercices (intention · séries × reps · repos) |
|---|---|
| **Lun · Poussée FORCE MAX** | Pompes claquées 💥 4×3 · 120 s ; Développé couché (barre) 🔥 5×2–3 · 240 s ; Développé incliné haltères 🧱 4×6–8 · 120 s ; Dips 🔥 4×3–5 · 180 s ; Barre au front 🧱 3×8–10 · 90 s ; Crunch à la poulie 🧱 3×8–12 · 60 s |
| **Mar · Tirage FORCE MAX** | Tractions pronation 💥 4×3 · 120 s ; Rowing barre 🔥 5×3–5 · 180 s ; Tractions supination 🔥 4×3–5 · 180 s ; Rowing haltère unilatéral 🧱 3×6–8 · 120 s ; Curl barre 🧱 4×6–8 · 90 s ; Curl marteau 🧱 3×8–10 · 90 s ; Relevés de jambes suspendu 🧱 3×8–12 · 60 s |
| **Mer · Épaules PUISSANCE** | Développé poussé (barre) 💥 5×3 · 150 s ; Développé militaire (barre) 🔥 5×3–5 · 180 s ; Élévations latérales 🧱 3×10–12 · 60 s ; Oiseau haltères 🧱 3×10–12 · 60 s ; Face pull 🧱 3×12–15 · 60 s ; Crunch 🧱 3×10–15 · 60 s |
| **Jeu · Haut du corps VITESSE** | Développé couché (barre) ⚡ 8×3 · 60 s ; Rowing barre ⚡ 6×3 · 60 s ; Pompes diamant 💥 4×5 · 90 s ; Dips 🧱 3×8–10 · 90 s ; Curl marteau 🧱 3×8–10 · 60 s ; Extension triceps à la poulie 🧱 3×10–12 · 60 s ; Relevés de jambes suspendu 🧱 3×8–12 · 60 s |
| **Ven · Jambes & hanches EXPLOSIVES** | Sauts sur box 💥 5×3 · 120 s ; Saut en longueur 💥 4×3 · 120 s ; Squat (barre) ⚡ 8×2 · 60 s ; Soulevé de terre ⚡ 6×2 · 90 s ; Squat sauté 💥 3×5 · 120 s ; Hip thrust 🔥 4×3–5 · 180 s ; Crunch à la poulie 🧱 3×8–12 · 60 s |

L'ancien programme reste dans l'historique ; la quête du jour en attente garde son type (calibrage tant qu'Aïssam n'est pas calibré : le test portera alors sur développé couché, tirage, squat et soulevé de terre, tous présents dans le programme).

## 6. Tests

- Charges par intention : vitesse 57,5 %, force max 87,5 %, explosif chargé 40 %, explosif au poids du corps sans charge, masse inchangée ; meilleur 1RM = max(calibrage, record) ; repli sur la charge déduite.
- Progression : force max et masse augmentent comme aujourd'hui ; vitesse et explosif n'augmentent pas par les répétitions mais suivent un nouveau record.
- Affichage : icône et consigne dans la quête, la séance et la page Programme ; conseil « corps sec ».
- Nouveaux exercices : présents au catalogue avec fiche et animation distincte ; `jump` compté bas du corps et absent des journées générées.
- Programme d'Aïssam : 5 jours exacts, intentions et prescriptions conformes au tableau.
