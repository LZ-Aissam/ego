# EGO — Objectif « Force pure » (mode Anatoly)

## 1. Objectif

Force relative maximale sans prise de volume : séries de 1 à 5 répétitions très lourdes, peu de volume d'hypertrophie, squat · couché · soulevé de terre deux fois par semaine, alimentation à maintien. Nouvel objectif du questionnaire « Force pure », et nouveau programme actif d'Aïssam (4 jours : lundi, mardi, jeudi, vendredi).

**Hors périmètre** : périodisation par blocs et semaine de records automatiques (la progression reste la double progression avec décharge après 2 échecs) ; Force × Vitesse et les autres objectifs ne changent pas.

## 2. Intention 🏆 RECORD (`top`)

- Charge : 92,5 % du meilleur 1RM estimé, arrondie vers le bas à 2,5 kg (comme 🔥), jamais stockée : elle suit le 1RM, qui monte avec les records (une série réussie à 92,5 % fait monter le 1RM estimé, donc la charge de la semaine suivante).
- Consigne : « Une seule série très lourde : 1 à 2 répétitions parfaites, puis repos complet avant la suite. »
- Progression : aucune par les répétitions. **Les séries faites à la charge 🏆 (ou plus lourd) ne jugent pas la charge 🔥 du même exercice** : ni échec, ni réussite.
- Débutant : la ligne 🏆 est retirée.

## 3. Modèle « Force pure »

Fichier `database/data/programs/pure-templates.php`, même format et mêmes adaptations que Force × Vitesse (`PowerProgram`, paramétré par fichier de modèle). Nouveau marqueur de préférence `@same` : la ligne reprend l'exercice de la ligne précédente (série 🏆 puis séries 🔥 du même mouvement).

| Séance | Lignes |
|---|---|
| **Couché · LOURD** | horizontal_push 💥 4×3 · 120 [clap-push-up] ; horizontal_push 🏆 1×1–2 · 300 [bench-press, dumbbell-bench-press, push-up] ; horizontal_push 🔥 4×2–3 · 240 [@same] ; vertical_pull 🔥 5×3 · 180 [pull-up, chin-up, inverted-row] ; shoulder_iso 🧱 3×12–15 · 60 [face-pull, rear-delt-fly] ; core 🧱 3×8–12 · 60 [hanging-leg-raise, crunch] |
| **Squat · LOURD** | jump 💥 4×3 · 120 [box-jump] ; squat 🔥 5×2–3 · 240 [back-squat, goblet-squat, bodyweight-squat] ; vertical_push 🔥 5×3–5 · 180 [overhead-press, dumbbell-shoulder-press, pike-push-up] ; core 🧱 3×8–12 · 60 [cable-crunch, crunch] |
| **Haut du corps · VITESSE** | horizontal_push ⚡ 6×2 · 60 [bench-press, dumbbell-bench-press, clap-push-up] ; horizontal_pull 🔥 5×3–5 · 180 [barbell-row, dumbbell-row, inverted-row] ; triceps 🔥 4×3–5 · 180 [dips] ; shoulder_iso 🧱 3×12–15 · 60 [lateral-raise, cable-lateral-raise] ; core 🧱 3×8–12 · 60 [hanging-leg-raise, crunch] |
| **Soulevé de terre · LOURD** | jump 💥 4×3 · 120 [broad-jump] ; hinge 🏆 1×1–2 · 300 [deadlift, dumbbell-romanian-deadlift, glute-bridge] ; hinge 🔥 3×2–3 · 240 [@same] ; squat ⚡ 6×2 · 90 [back-squat, goblet-squat, bodyweight-squat] ; hinge 🔥 4×3–5 · 180 [hip-thrust, glute-bridge] ; core 🧱 3×8–12 · 60 [cable-crunch, crunch] |
| **Épaules & tractions · VITESSE** (5 j +) | vertical_push 💥 5×3 · 150 [push-press, dumbbell-shoulder-press, pike-push-up] ; vertical_push ⚡ 6×2 · 90 [overhead-press, dumbbell-shoulder-press, pike-push-up] ; vertical_pull 💥 5×3 · 120 [pull-up, chin-up] ; shoulder_iso 🧱 3×12–15 · 60 [face-pull, rear-delt-fly] ; core 🧱 3×10–15 · 60 [crunch] |
| **Jambes · VITESSE** (6 j) | jump 💥 5×3 · 120 [box-jump] ; jump 💥 3×5 · 120 [jump-squat] ; squat ⚡ 8×2 · 60 [back-squat, goblet-squat, bodyweight-squat] ; hinge ⚡ 6×2 · 90 [deadlift, dumbbell-romanian-deadlift, glute-bridge] ; core 🧱 3×8–12 · 60 [cable-crunch, crunch] |
| **Full · COUCHÉ & SQUAT** (2 j) | horizontal_push 🏆 1×1–2 · 300 [bench-press, dumbbell-bench-press, push-up] ; horizontal_push 🔥 4×2–3 · 240 [@same] ; squat 🔥 5×2–3 · 240 [back-squat, goblet-squat, bodyweight-squat] ; vertical_pull 🔥 5×3 · 180 [pull-up, chin-up, inverted-row] ; core 🧱 3×8–12 · 60 [hanging-leg-raise, crunch] |
| **Full · TERRE & ÉPAULES** (2 j) | hinge 🏆 1×1–2 · 300 [deadlift, dumbbell-romanian-deadlift, glute-bridge] ; hinge 🔥 3×2–3 · 240 [@same] ; vertical_push 🔥 5×3–5 · 180 [overhead-press, dumbbell-shoulder-press, pike-push-up] ; horizontal_pull 🔥 5×3–5 · 180 [barbell-row, dumbbell-row, inverted-row] ; core 🧱 3×8–12 · 60 [cable-crunch, crunch] |

| Jours | Séances |
|---|---|
| 2 | Full COUCHÉ & SQUAT · Full TERRE & ÉPAULES |
| 3 | Couché · Squat · Soulevé de terre |
| 4 | Couché · Squat · Haut VITESSE · Soulevé de terre |
| 5 | + Épaules & tractions VITESSE |
| 6 | + Jambes VITESSE |

## 4. Questionnaire, programme, conseils

- Objectif `pure`, libellé « Force pure », programme « Programme Force pure · N j », `split` = `pure` ; séances attachées aux jours de la semaine comme Force × Vitesse.
- Conseil en tête de la page Programme pour un programme `pure` : « Force pure : mange à maintien (ni déficit ni surplus), 1,8 à 2 g de protéines par kg, et dors au moins 7 h 30. » Les autres programmes à intentions gardent le conseil actuel.
- Programme d'Aïssam (base locale) : régénéré en Force pure, avancé, salle, lundi · mardi · jeudi · vendredi. En ligne, il le régénère lui-même depuis le questionnaire.

## 5. Tests

- 🏆 : 92,5 % arrondi vers le bas ; une séance avec la série 🏆 à 1 répétition puis les séries 🔥 réussies fait monter la charge 🔥 ; une série 🏆 seule n'est pas un échec.
- Modèle : 4 j salle avancé = tableau ci-dessus ; `@same` reprend l'exercice précédent ; débutant sans 🏆 ; matériel respecté ; séances 2 à 6 j dans l'ordre.
- Questionnaire : objectif proposé et accepté ; jours attachés ; conseil « maintien » affiché.
