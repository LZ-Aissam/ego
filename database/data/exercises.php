<?php

// [slug, nom, muscles, matériel, type, emplacements, ratio homme débutant | null = poids du corps]

return [
    // Poussée horizontale
    ['bench-press', 'Développé couché (barre)', ['pectoraux', 'triceps', 'épaules'], ['gym'], 'compound', ['horizontal_push'], 0.5],
    ['dumbbell-bench-press', 'Développé couché haltères', ['pectoraux', 'triceps', 'épaules'], ['gym', 'dumbbells'], 'compound', ['horizontal_push'], 0.2],
    ['incline-bench-press', 'Développé incliné (barre)', ['pectoraux', 'épaules', 'triceps'], ['gym'], 'compound', ['horizontal_push'], 0.4],
    ['incline-dumbbell-press', 'Développé incliné haltères', ['pectoraux', 'épaules', 'triceps'], ['gym', 'dumbbells'], 'compound', ['horizontal_push'], 0.17],
    ['machine-chest-press', 'Presse à pectoraux (machine)', ['pectoraux', 'triceps'], ['gym'], 'compound', ['horizontal_push'], 0.4],
    ['push-up', 'Pompes', ['pectoraux', 'triceps', 'épaules'], ['bodyweight', 'dumbbells', 'gym'], 'compound', ['horizontal_push'], null],
    ['dips', 'Dips', ['pectoraux', 'triceps'], ['gym', 'bodyweight'], 'compound', ['horizontal_push', 'triceps'], null],
    // Poussée verticale
    ['overhead-press', 'Développé militaire (barre)', ['épaules', 'triceps'], ['gym'], 'compound', ['vertical_push'], 0.35],
    ['dumbbell-shoulder-press', 'Développé épaules haltères', ['épaules', 'triceps'], ['gym', 'dumbbells'], 'compound', ['vertical_push'], 0.13],
    ['arnold-press', 'Développé Arnold', ['épaules', 'triceps'], ['gym', 'dumbbells'], 'compound', ['vertical_push'], 0.11],
    ['machine-shoulder-press', 'Développé épaules (machine)', ['épaules', 'triceps'], ['gym'], 'compound', ['vertical_push'], 0.3],
    ['pike-push-up', 'Pompes piquées', ['épaules', 'triceps'], ['bodyweight'], 'compound', ['vertical_push'], null],
    // Tirage horizontal
    ['barbell-row', 'Rowing barre', ['dos', 'biceps'], ['gym'], 'compound', ['horizontal_pull'], 0.4],
    ['dumbbell-row', 'Rowing haltère unilatéral', ['dos', 'biceps'], ['gym', 'dumbbells'], 'compound', ['horizontal_pull'], 0.2],
    ['chest-supported-row', 'Rowing buste appuyé haltères', ['dos', 'biceps'], ['gym', 'dumbbells'], 'compound', ['horizontal_pull'], 0.15],
    ['seated-cable-row', 'Tirage horizontal à la poulie', ['dos', 'biceps'], ['gym'], 'compound', ['horizontal_pull'], 0.4],
    ['inverted-row', 'Rowing inversé', ['dos', 'biceps'], ['bodyweight', 'gym'], 'compound', ['horizontal_pull'], null],
    // Tirage vertical
    ['pull-up', 'Tractions pronation', ['dos', 'biceps'], ['gym', 'bodyweight'], 'compound', ['vertical_pull'], null],
    ['chin-up', 'Tractions supination', ['dos', 'biceps'], ['gym', 'bodyweight'], 'compound', ['vertical_pull'], null],
    ['lat-pulldown', 'Tirage vertical à la poulie', ['dos', 'biceps'], ['gym'], 'compound', ['vertical_pull'], 0.45],
    ['dumbbell-pullover', 'Pull-over haltère', ['dos', 'pectoraux'], ['gym', 'dumbbells'], 'isolation', ['vertical_pull'], 0.12],
    // Squat
    ['back-squat', 'Squat (barre)', ['quadriceps', 'fessiers'], ['gym'], 'compound', ['squat'], 0.6],
    ['front-squat', 'Squat avant', ['quadriceps', 'fessiers'], ['gym'], 'compound', ['squat'], 0.5],
    ['leg-press', 'Presse à cuisses', ['quadriceps', 'fessiers'], ['gym'], 'compound', ['squat'], 1.0],
    ['goblet-squat', 'Squat goblet', ['quadriceps', 'fessiers'], ['gym', 'dumbbells'], 'compound', ['squat'], 0.2],
    ['bodyweight-squat', 'Squat au poids du corps', ['quadriceps', 'fessiers'], ['bodyweight'], 'compound', ['squat'], null],
    // Charnière de hanche
    ['deadlift', 'Soulevé de terre', ['ischio-jambiers', 'fessiers', 'dos'], ['gym'], 'compound', ['hinge'], 0.75],
    ['romanian-deadlift', 'Soulevé de terre roumain (barre)', ['ischio-jambiers', 'fessiers'], ['gym'], 'compound', ['hinge'], 0.5],
    ['dumbbell-romanian-deadlift', 'Soulevé de terre roumain haltères', ['ischio-jambiers', 'fessiers'], ['gym', 'dumbbells'], 'compound', ['hinge'], 0.2],
    ['hip-thrust', 'Hip thrust', ['fessiers', 'ischio-jambiers'], ['gym'], 'compound', ['hinge'], 0.6],
    ['glute-bridge', 'Pont fessier', ['fessiers', 'ischio-jambiers'], ['bodyweight', 'dumbbells'], 'compound', ['hinge'], null],
    // Fentes
    ['walking-lunge', 'Fentes marchées haltères', ['quadriceps', 'fessiers'], ['gym', 'dumbbells'], 'compound', ['lunge'], 0.12],
    ['bulgarian-split-squat', 'Squat bulgare haltères', ['quadriceps', 'fessiers'], ['gym', 'dumbbells'], 'compound', ['lunge'], 0.1],
    ['step-up', 'Montées sur banc haltères', ['quadriceps', 'fessiers'], ['gym', 'dumbbells'], 'compound', ['lunge'], 0.1],
    ['bodyweight-lunge', 'Fentes au poids du corps', ['quadriceps', 'fessiers'], ['bodyweight'], 'compound', ['lunge'], null],
    // Pectoraux (isolation)
    ['dumbbell-fly', 'Écarté couché haltères', ['pectoraux'], ['gym', 'dumbbells'], 'isolation', ['chest_iso'], 0.08],
    ['cable-fly', 'Écarté à la poulie', ['pectoraux'], ['gym'], 'isolation', ['chest_iso'], 0.1],
    ['pec-deck', 'Pec deck', ['pectoraux'], ['gym'], 'isolation', ['chest_iso'], 0.3],
    // Épaules (isolation)
    ['lateral-raise', 'Élévations latérales', ['épaules'], ['gym', 'dumbbells'], 'isolation', ['shoulder_iso'], 0.05],
    ['rear-delt-fly', 'Oiseau haltères', ['épaules', 'dos'], ['gym', 'dumbbells'], 'isolation', ['shoulder_iso'], 0.05],
    ['face-pull', 'Face pull', ['épaules', 'dos'], ['gym'], 'isolation', ['shoulder_iso'], 0.2],
    ['cable-lateral-raise', 'Élévations latérales à la poulie', ['épaules'], ['gym'], 'isolation', ['shoulder_iso'], 0.06],
    // Biceps
    ['barbell-curl', 'Curl barre', ['biceps'], ['gym'], 'isolation', ['biceps'], 0.2],
    ['dumbbell-curl', 'Curl haltères', ['biceps'], ['gym', 'dumbbells'], 'isolation', ['biceps'], 0.08],
    ['hammer-curl', 'Curl marteau', ['biceps', 'avant-bras'], ['gym', 'dumbbells'], 'isolation', ['biceps'], 0.08],
    ['cable-curl', 'Curl à la poulie', ['biceps'], ['gym'], 'isolation', ['biceps'], 0.15],
    // Triceps
    ['triceps-pushdown', 'Extension triceps à la poulie', ['triceps'], ['gym'], 'isolation', ['triceps'], 0.2],
    ['overhead-dumbbell-extension', 'Extension triceps haltère au-dessus de la tête', ['triceps'], ['gym', 'dumbbells'], 'isolation', ['triceps'], 0.1],
    ['skull-crusher', 'Barre au front', ['triceps'], ['gym'], 'isolation', ['triceps'], 0.15],
    ['diamond-push-up', 'Pompes diamant', ['triceps', 'pectoraux'], ['bodyweight'], 'isolation', ['triceps'], null],
    ['bench-dips', 'Dips sur banc', ['triceps'], ['bodyweight', 'dumbbells'], 'isolation', ['triceps'], null],
    // Quadriceps (isolation)
    ['leg-extension', 'Leg extension', ['quadriceps'], ['gym'], 'isolation', ['quads_iso'], 0.3],
    ['sissy-squat', 'Sissy squat', ['quadriceps'], ['bodyweight'], 'isolation', ['quads_iso'], null],
    // Ischio-jambiers (isolation)
    ['leg-curl', 'Leg curl', ['ischio-jambiers'], ['gym'], 'isolation', ['hamstrings_iso'], 0.25],
    ['nordic-curl', 'Nordic curl', ['ischio-jambiers'], ['bodyweight'], 'isolation', ['hamstrings_iso'], null],
    // Mollets
    ['standing-calf-raise', 'Mollets debout (machine)', ['mollets'], ['gym'], 'isolation', ['calves'], 0.5],
    ['dumbbell-calf-raise', 'Mollets debout haltères', ['mollets'], ['gym', 'dumbbells'], 'isolation', ['calves'], 0.1],
    ['bodyweight-calf-raise', 'Mollets au poids du corps', ['mollets'], ['bodyweight'], 'isolation', ['calves'], null],
    // Abdominaux
    ['crunch', 'Crunch', ['abdominaux'], ['bodyweight', 'dumbbells', 'gym'], 'isolation', ['core'], null],
    ['hanging-leg-raise', 'Relevés de jambes suspendu', ['abdominaux'], ['gym', 'bodyweight'], 'isolation', ['core'], null],
    ['cable-crunch', 'Crunch à la poulie', ['abdominaux'], ['gym'], 'isolation', ['core'], 0.3],
    // Puissance et explosivité
    ['push-press', 'Développé poussé (barre)', ['épaules', 'triceps', 'jambes'], ['gym'], 'compound', ['vertical_push'], 0.4],
    ['clap-push-up', 'Pompes claquées', ['pectoraux', 'triceps', 'épaules'], ['bodyweight', 'dumbbells', 'gym'], 'compound', ['horizontal_push'], null],
    ['box-jump', 'Sauts sur box', ['quadriceps', 'fessiers', 'mollets'], ['bodyweight', 'dumbbells', 'gym'], 'compound', ['jump'], null],
    ['broad-jump', 'Saut en longueur', ['fessiers', 'quadriceps', 'ischio-jambiers'], ['bodyweight', 'dumbbells', 'gym'], 'compound', ['jump'], null],
    ['jump-squat', 'Squat sauté', ['quadriceps', 'fessiers', 'mollets'], ['bodyweight', 'dumbbells', 'gym'], 'compound', ['jump'], null],
    // Avant-bras
    ['wrist-curl', 'Curl poignets', ['avant-bras'], ['gym', 'dumbbells'], 'isolation', ['forearms'], 0.06],
    ['wrist-extension', 'Extension des poignets', ['avant-bras'], ['gym', 'dumbbells'], 'isolation', ['forearms'], 0.04],
    ['reverse-curl', 'Curl inversé', ['avant-bras', 'biceps'], ['gym', 'dumbbells'], 'isolation', ['forearms', 'biceps'], 0.06],
];
