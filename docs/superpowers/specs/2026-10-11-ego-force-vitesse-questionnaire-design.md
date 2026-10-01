# EGO — Objectif « Force × Vitesse » dans le questionnaire

## 1. Objectif et périmètre

Tout joueur peut choisir **Force × Vitesse** comme objectif dans le questionnaire de programme (et via « Régénérer le programme »). Le générateur construit alors un programme à intentions (⚡ vitesse, 🔥 force max, 💥 explosif, 🧱 masse — livraison F) adapté à son niveau, son matériel et son nombre de jours, avec toujours une séance jambes et hanches explosives en fin de semaine.

**Hors périmètre** : la commande `ego:programme-force-vitesse` et le fichier `database/data/programs/force-vitesse.php` restent tels quels ; le programme actif d'Aïssam n'est pas touché ; pas de nouvel exercice ; les trois objectifs existants gardent leur comportement exact.

## 2. Questionnaire et programme créé

- Nouvelle valeur d'objectif `power`, libellé **« Force × Vitesse »**, 4ᵉ choix après Force, Prise de muscle, Remise en forme (`ProgramBuilder::GOALS`, validation `in:strength,hypertrophy,fitness,power`).
- Programme créé : nom « Programme Force × Vitesse · N j », `split` = `power`, `params` = `{level, goal: power, equipment, weekdays}`.
- **Jours attachés à la semaine** : comme les programmes personnalisés, un programme `power` dont le nombre de jours égale le nombre de jours cochés associe chaque séance à son jour de la semaine (1ʳᵉ séance = 1ᵉʳ jour coché…), au lieu de la rotation. La séance explosive jambes tombe donc toujours le dernier jour coché.

## 3. Modèle de séances

Fichier `database/data/programs/power-templates.php` : pour chaque séance, un nom, un type et des lignes `[emplacement, intention, séries, reps min, reps max, repos s, [slugs préférés]]`. Ordre des lignes = ordre d'exécution (explosif → force max / vitesse → masse → abdos).

**Choix de l'exercice d'une ligne** : le premier slug préféré disponible pour le matériel du joueur et pas encore utilisé dans la séance ; à défaut, le premier candidat de l'emplacement (`ProgramGenerator::candidates`, même matériel, pas encore utilisé) ; à défaut, la ligne est omise.

| Séance | Lignes (intention · séries × reps · repos) |
|---|---|
| **Poussée · FORCE MAX** | horizontal_push 💥 4×3 · 120 [clap-push-up] ; horizontal_push 🔥 5×2–3 · 240 [bench-press, dumbbell-bench-press, push-up] ; horizontal_push 🧱 4×6–8 · 120 [incline-dumbbell-press, dumbbell-bench-press, push-up] ; triceps 🔥 4×3–5 · 180 [dips, bench-dips] ; triceps 🧱 3×8–10 · 90 [skull-crusher, overhead-dumbbell-extension, diamond-push-up] ; core 🧱 3×8–12 · 60 [cable-crunch, crunch] |
| **Tirage · FORCE MAX** | vertical_pull 💥 4×3 · 120 [pull-up] ; horizontal_pull 🔥 5×3–5 · 180 [barbell-row, dumbbell-row, inverted-row] ; vertical_pull 🔥 4×3–5 · 180 [chin-up] ; horizontal_pull 🧱 3×6–8 · 120 [dumbbell-row, chest-supported-row, inverted-row] ; biceps 🧱 4×6–8 · 90 [barbell-curl, dumbbell-curl] ; biceps 🧱 3×8–10 · 90 [hammer-curl] ; core 🧱 3×8–12 · 60 [hanging-leg-raise, crunch] |
| **Épaules · PUISSANCE** | vertical_push 💥 5×3 · 150 [push-press, dumbbell-shoulder-press, pike-push-up] ; vertical_push 🔥 5×3–5 · 180 [overhead-press, dumbbell-shoulder-press, pike-push-up] ; shoulder_iso 🧱 3×10–12 · 60 [lateral-raise] ; shoulder_iso 🧱 3×10–12 · 60 [rear-delt-fly] ; shoulder_iso 🧱 3×12–15 · 60 [face-pull] ; core 🧱 3×10–15 · 60 [crunch] |
| **Haut du corps · VITESSE** | horizontal_push ⚡ 8×3 · 60 [bench-press, dumbbell-bench-press, clap-push-up] ; horizontal_pull ⚡ 6×3 · 60 [barbell-row, dumbbell-row, inverted-row] ; triceps 💥 4×5 · 90 [diamond-push-up, clap-push-up] ; triceps 🧱 3×8–10 · 90 [dips, bench-dips] ; biceps 🧱 3×8–10 · 60 [hammer-curl, dumbbell-curl] ; triceps 🧱 3×10–12 · 60 [triceps-pushdown, overhead-dumbbell-extension] ; core 🧱 3×8–12 · 60 [hanging-leg-raise, crunch] |
| **Haut du corps · FORCE MAX** (2 j) | horizontal_push 💥 4×3 · 120 [clap-push-up] ; horizontal_push 🔥 5×2–3 · 240 [bench-press, dumbbell-bench-press, push-up] ; horizontal_pull 🔥 5×3–5 · 180 [barbell-row, dumbbell-row, inverted-row] ; vertical_push 🔥 4×3–5 · 180 [overhead-press, dumbbell-shoulder-press, pike-push-up] ; vertical_pull 🔥 4×3–5 · 180 [chin-up, pull-up] ; biceps 🧱 3×8–10 · 90 [hammer-curl, dumbbell-curl] ; core 🧱 3×8–12 · 60 [hanging-leg-raise, crunch] |
| **Jambes · FORCE MAX** (6 j) | jump 💥 3×3 · 120 [jump-squat] ; squat 🔥 5×2–3 · 240 [back-squat, goblet-squat, bodyweight-squat] ; hinge 🔥 4×2–3 · 240 [deadlift, dumbbell-romanian-deadlift, glute-bridge] ; lunge 🧱 3×6–8 · 120 [bulgarian-split-squat, bodyweight-lunge] ; hamstrings_iso 🧱 3×6–10 · 90 [leg-curl, nordic-curl] ; calves 🧱 3×10–12 · 60 [standing-calf-raise, dumbbell-calf-raise, bodyweight-calf-raise] ; core 🧱 3×8–12 · 60 [cable-crunch, crunch] |
| **Jambes & hanches · EXPLOSIF** | jump 💥 5×3 · 120 [box-jump] ; jump 💥 4×3 · 120 [broad-jump] ; squat ⚡ 8×2 · 60 [back-squat, goblet-squat, bodyweight-squat] ; hinge ⚡ 6×2 · 90 [deadlift, dumbbell-romanian-deadlift, glute-bridge] ; jump 💥 3×5 · 120 [jump-squat] ; hinge 🔥 4×3–5 · 180 [hip-thrust, glute-bridge] ; core 🧱 3×8–12 · 60 [cable-crunch, crunch] |

**Séances selon le nombre de jours** (dans l'ordre de la semaine) :

| Jours | Séances |
|---|---|
| 2 | Haut du corps FORCE MAX · Jambes & hanches EXPLOSIF |
| 3 | Poussée · Tirage · Jambes & hanches EXPLOSIF |
| 4 | Poussée · Tirage · Haut du corps VITESSE · Jambes & hanches EXPLOSIF |
| 5 | Poussée · Tirage · Épaules · Haut du corps VITESSE · Jambes & hanches EXPLOSIF |
| 6 | Poussée · Tirage · Jambes FORCE MAX · Épaules · Haut du corps VITESSE · Jambes & hanches EXPLOSIF |

**Repère** : 5 jours, salle complète, avancé → exactement le programme de `force-vitesse.php` (mêmes exercices, intentions, séries, reps, repos, dans le même ordre).

## 4. Adaptations

Appliquées ligne par ligne, dans cet ordre :

1. **Niveau — nombre de lignes** : au plus 5 (débutant), 6 (intermédiaire), 7 (avancé) lignes par séance (`SLOTS_BY_LEVEL`). On garde les premières lignes non-abdos et la ligne abdos (toujours en dernier).
2. **Poids du corps** (matériel `bodyweight`) : une ligne 🔥 dont l'exercice choisi est au poids du corps devient 🧱 en 5–8 reps (mêmes séries) ; une ligne ⚡ dont l'exercice choisi est au poids du corps devient 💥 (mêmes séries et reps). En salle et aux haltères, rien ne change (tractions ou dips 🔥 se font lestés).
3. **Débutant** : une ligne 🔥 devient 🧱 en 4–6 reps avec une série de moins (minimum 3) ; ⚡, 💥 et 🧱 inchangées.

## 5. Tests

- Questionnaire : l'objectif « Force × Vitesse » est proposé et accepté ; le programme créé s'appelle « Programme Force × Vitesse · N j », a des intentions et le split `power`.
- 5 j salle avancé = `force-vitesse.php` ligne pour ligne.
- 2, 3, 4, 6 jours : bons noms de séance dans le bon ordre, la dernière est « Jambes & hanches · EXPLOSIF ».
- Haltères : aucun exercice réservé à la salle ; poids du corps : aucun exercice hors poids du corps, aucune ligne 🔥 ni ⚡ sur un exercice sans charge.
- Débutant : ≤ 5 lignes, aucune 🔥 ; intermédiaire : ≤ 6 lignes ; abdos en dernier.
- Jours attachés : un programme `power` 3 j (lun, mer, ven) donne Jambes & hanches le vendredi.
- Les objectifs Force, Prise de muscle, Remise en forme génèrent exactement comme avant (tests existants verts).
