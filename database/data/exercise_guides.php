<?php

// Fiches « Comment faire ? » : muscles, tempo, 3 ou 4 consignes, l'erreur à éviter.

return [
    // Poussée horizontale
    'bench-press' => [
        'muscles' => 'Pectoraux · triceps · épaules',
        'tempo' => 'Descente 2 s · Poussée 1 s',
        'steps' => [
            'Allonge-toi les yeux sous la barre, pieds à plat au sol. Serre les omoplates et descends-les vers les fesses.',
            'Prise un peu plus large que les épaules, poignets droits, alignés au-dessus des coudes.',
            "Descends la barre en contrôle jusqu'au bas des pectoraux, coudes à environ 45° du buste.",
            "Pousse vers le haut et légèrement vers l'arrière jusqu'aux bras tendus, fesses collées au banc.",
        ],
        'mistake' => 'Barre qui rebondit sur la poitrine, coudes écartés à 90°, fesses qui décollent du banc.',
    ],
    'dumbbell-bench-press' => [
        'muscles' => 'Pectoraux · triceps · épaules',
        'tempo' => 'Descente 2 s · Poussée 1 s',
        'steps' => [
            'Assieds-toi, haltères sur les cuisses, puis allonge-toi en les amenant au-dessus de la poitrine.',
            'Omoplates serrées, pieds au sol, paumes vers tes pieds.',
            'Descends les haltères sur les côtés de la poitrine, coudes à environ 45° du buste.',
            'Pousse en rapprochant légèrement les haltères en haut, sans les cogner.',
        ],
        'mistake' => 'Descendre trop bas en écartant les coudes, ce qui charge les épaules.',
    ],
    'incline-bench-press' => [
        'muscles' => 'Haut des pectoraux · épaules · triceps',
        'tempo' => 'Descente 2 s · Poussée 1 s',
        'steps' => [
            'Règle le banc entre 30° et 45°. Pieds au sol, omoplates serrées.',
            'Prise un peu plus large que les épaules, poignets droits.',
            'Descends la barre en contrôle vers le haut de la poitrine, sous les clavicules.',
            'Pousse à la verticale jusqu\'aux bras tendus.',
        ],
        'mistake' => 'Banc trop incliné : l\'exercice devient un développé épaules.',
    ],
    'incline-dumbbell-press' => [
        'muscles' => 'Haut des pectoraux · épaules · triceps',
        'tempo' => 'Descente 2 s · Poussée 1 s',
        'steps' => [
            'Banc entre 30° et 45°, haltères au-dessus du haut de la poitrine.',
            'Omoplates serrées, pieds au sol.',
            'Descends les haltères sur les côtés du haut de la poitrine, coudes sous les poignets.',
            'Pousse vers le haut en rapprochant légèrement les haltères.',
        ],
        'mistake' => 'Lombaires qui se creusent fort pour soulever plus lourd.',
    ],
    'machine-chest-press' => [
        'muscles' => 'Pectoraux · triceps · épaules',
        'tempo' => 'Poussée 1 s · Retour 2 s',
        'steps' => [
            'Règle le siège pour que les poignées arrivent à hauteur du milieu de la poitrine.',
            'Dos collé au dossier, omoplates serrées.',
            'Pousse les poignées devant toi jusqu\'aux bras presque tendus.',
            'Reviens lentement sans laisser la charge toucher la pile.',
        ],
        'mistake' => 'Épaules qui avancent et décollent du dossier en fin de poussée.',
    ],
    'push-up' => [
        'muscles' => 'Pectoraux · triceps · épaules · gainage',
        'tempo' => 'Descente 2 s · Poussée 1 s',
        'steps' => [
            'Mains un peu plus larges que les épaules, corps aligné des talons à la tête.',
            'Serre les fessiers et le ventre pour garder le dos plat.',
            'Descends la poitrine près du sol, coudes à environ 45° du buste.',
            'Pousse le sol pour revenir bras tendus.',
        ],
        'mistake' => 'Bassin qui s\'affaisse ou fesses en l\'air.',
    ],
    'dips' => [
        'muscles' => 'Pectoraux · triceps · épaules',
        'tempo' => 'Descente 2 s · Poussée 1 s',
        'steps' => [
            'Bras tendus sur les barres parallèles, épaules basses, loin des oreilles.',
            'Penche légèrement le buste vers l\'avant, jambes pliées derrière toi.',
            'Descends jusqu\'à ce que les épaules arrivent au niveau des coudes.',
            'Pousse jusqu\'aux bras tendus sans bloquer les coudes brutalement.',
        ],
        'mistake' => 'Descendre trop bas, épaules qui roulent vers l\'avant.',
    ],
    // Poussée verticale
    'overhead-press' => [
        'muscles' => 'Épaules · triceps · gainage',
        'tempo' => 'Poussée 1 s · Descente 2 s',
        'steps' => [
            'Debout, pieds largeur de hanches, barre posée en haut de la poitrine, coudes légèrement devant.',
            'Serre les fessiers et le ventre : le corps forme un bloc.',
            'Pousse la barre à la verticale en reculant un peu la tête, puis passe la tête sous la barre en haut.',
            'Redescends en contrôle jusqu\'au haut de la poitrine.',
        ],
        'mistake' => 'Se cambrer en arrière pour finir la poussée.',
    ],
    'dumbbell-shoulder-press' => [
        'muscles' => 'Épaules · triceps',
        'tempo' => 'Poussée 1 s · Descente 2 s',
        'steps' => [
            'Assis, dos contre le dossier, haltères à hauteur des oreilles.',
            'Coudes légèrement devant le corps, poignets au-dessus des coudes.',
            'Pousse les haltères au-dessus de la tête sans les cogner.',
            'Redescends lentement jusqu\'aux oreilles.',
        ],
        'mistake' => 'Décoller le dos du dossier et se cambrer.',
    ],
    'arnold-press' => [
        'muscles' => 'Épaules · triceps',
        'tempo' => 'Poussée 1 s · Descente 2 s',
        'steps' => [
            'Assis, haltères devant le visage, paumes tournées vers toi.',
            'Pousse vers le haut en tournant les poignets : paumes vers l\'avant en haut.',
            'Fais le mouvement inverse en redescendant, paumes vers toi en bas.',
        ],
        'mistake' => 'Rotation trop rapide qui fait perdre le contrôle des haltères.',
    ],
    'machine-shoulder-press' => [
        'muscles' => 'Épaules · triceps',
        'tempo' => 'Poussée 1 s · Descente 2 s',
        'steps' => [
            'Règle le siège pour que les poignées soient à hauteur des épaules.',
            'Dos collé au dossier, pieds au sol.',
            'Pousse jusqu\'aux bras presque tendus.',
            'Redescends lentement sans poser la charge.',
        ],
        'mistake' => 'Hausser les épaules vers les oreilles pendant la poussée.',
    ],
    'pike-push-up' => [
        'muscles' => 'Épaules · triceps',
        'tempo' => 'Descente 2 s · Poussée 1 s',
        'steps' => [
            'Mains au sol largeur d\'épaules, fesses en l\'air : le corps forme un V inversé.',
            'Plie les bras pour amener le haut de la tête vers le sol, entre les mains.',
            'Pousse fort pour revenir bras tendus.',
        ],
        'mistake' => 'Coudes qui s\'écartent sur les côtés.',
    ],
    // Tirage horizontal
    'barbell-row' => [
        'muscles' => 'Dos · biceps · arrière des épaules',
        'tempo' => 'Tirage 1 s · Retour 2 s',
        'steps' => [
            'Genoux légèrement fléchis, buste penché à environ 45°, dos plat.',
            'Barre bras tendus sous les épaules, prise un peu plus large que les épaules.',
            'Tire la barre vers le bas du ventre en ramenant les coudes en arrière.',
            'Serre les omoplates en haut, puis redescends en contrôle.',
        ],
        'mistake' => 'Se redresser et donner un coup de reins pour monter la barre.',
    ],
    'dumbbell-row' => [
        'muscles' => 'Dos · biceps',
        'tempo' => 'Tirage 1 s · Retour 2 s',
        'steps' => [
            'Main et genou du même côté sur le banc, dos plat parallèle au sol.',
            'Haltère bras tendu sous l\'épaule.',
            'Tire l\'haltère vers la hanche en gardant le coude près du corps.',
            'Redescends lentement jusqu\'au bras tendu.',
        ],
        'mistake' => 'Tourner le buste pour monter l\'haltère.',
    ],
    'chest-supported-row' => [
        'muscles' => 'Dos · biceps · arrière des épaules',
        'tempo' => 'Tirage 1 s · Retour 2 s',
        'steps' => [
            'Allonge-toi ventre contre un banc incliné, haltères bras tendus vers le sol.',
            'Garde la poitrine collée au banc pendant tout le mouvement.',
            'Tire les haltères vers les hanches en serrant les omoplates.',
            'Redescends en contrôle.',
        ],
        'mistake' => 'Décoller la poitrine du banc pour tricher.',
    ],
    'seated-cable-row' => [
        'muscles' => 'Dos · biceps',
        'tempo' => 'Tirage 1 s · Retour 2 s',
        'steps' => [
            'Assis, pieds sur les cales, genoux légèrement fléchis, dos droit.',
            'Tire la poignée vers le bas du ventre en ramenant les coudes en arrière.',
            'Serre les omoplates une seconde.',
            'Laisse les bras revenir devant toi sans arrondir le dos.',
        ],
        'mistake' => 'Se balancer d\'avant en arrière avec le buste.',
    ],
    'inverted-row' => [
        'muscles' => 'Dos · biceps · gainage',
        'tempo' => 'Tirage 1 s · Descente 2 s',
        'steps' => [
            'Sous une barre à hauteur de taille, mains un peu plus larges que les épaules.',
            'Corps gainé et droit des talons à la tête.',
            'Tire la poitrine vers la barre en serrant les omoplates.',
            'Redescends lentement jusqu\'aux bras tendus.',
        ],
        'mistake' => 'Bassin qui tombe pendant le tirage.',
    ],
    // Tirage vertical
    'pull-up' => [
        'muscles' => 'Dos (grands dorsaux) · biceps',
        'tempo' => 'Tirage 1 s · Descente 2 s',
        'steps' => [
            'Suspends-toi, paumes vers l\'avant, mains un peu plus larges que les épaules.',
            'Abaisse les épaules et gaine le ventre avant de tirer.',
            'Tire jusqu\'à passer le menton au-dessus de la barre, coudes vers les hanches.',
            'Redescends lentement jusqu\'aux bras tendus.',
        ],
        'mistake' => 'Se balancer ou s\'arrêter à mi-chemin en descendant.',
    ],
    'chin-up' => [
        'muscles' => 'Dos · biceps',
        'tempo' => 'Tirage 1 s · Descente 2 s',
        'steps' => [
            'Suspends-toi, paumes tournées vers toi, mains largeur d\'épaules.',
            'Abaisse les épaules et gaine le ventre.',
            'Tire jusqu\'à passer le menton au-dessus de la barre.',
            'Redescends lentement jusqu\'aux bras tendus.',
        ],
        'mistake' => 'Lancer les jambes pour monter.',
    ],
    'lat-pulldown' => [
        'muscles' => 'Dos (grands dorsaux) · biceps',
        'tempo' => 'Tirage 1 s · Retour 2 s',
        'steps' => [
            'Assis, cuisses calées sous le boudin, prise large sur la barre.',
            'Penche légèrement le buste en arrière, poitrine sortie.',
            'Tire la barre vers le haut de la poitrine en abaissant les coudes.',
            'Remonte lentement jusqu\'aux bras tendus.',
        ],
        'mistake' => 'Tirer la barre derrière la nuque ou avec un grand balancement.',
    ],
    'dumbbell-pullover' => [
        'muscles' => 'Dos · pectoraux',
        'tempo' => 'Descente 2 s · Retour 1 s',
        'steps' => [
            'Allongé sur un banc, un haltère tenu à deux mains au-dessus de la poitrine.',
            'Coudes légèrement fléchis et fixes pendant tout le mouvement.',
            'Descends l\'haltère derrière la tête en arc de cercle, jusqu\'à sentir l\'étirement.',
            'Ramène-le au-dessus de la poitrine.',
        ],
        'mistake' => 'Plier et tendre les coudes : l\'exercice devient un exercice de triceps.',
    ],
    // Squat
    'back-squat' => [
        'muscles' => 'Quadriceps · fessiers · gainage',
        'tempo' => 'Descente 2 s · Remontée 1 s',
        'steps' => [
            "Barre posée sur le haut du dos (trapèzes), jamais sur la nuque. Pieds largeur d'épaules, pointes légèrement ouvertes.",
            "Inspire, gaine le ventre, puis descends en poussant les hanches en arrière. Les genoux suivent l'axe des pieds.",
            "Descends au moins jusqu'aux cuisses parallèles au sol, le dos reste droit.",
            'Remonte en poussant dans tout le pied. La barre reste au-dessus du milieu du pied.',
        ],
        'mistake' => "Genoux qui rentrent vers l'intérieur, talons qui décollent, dos qui s'arrondit en bas du mouvement.",
    ],
    'front-squat' => [
        'muscles' => 'Quadriceps · fessiers · gainage',
        'tempo' => 'Descente 2 s · Remontée 1 s',
        'steps' => [
            'Barre posée devant les épaules, coudes hauts pointés vers l\'avant.',
            'Pieds largeur d\'épaules, buste le plus droit possible.',
            'Descends entre les talons jusqu\'aux cuisses parallèles.',
            'Remonte en gardant les coudes hauts.',
        ],
        'mistake' => 'Coudes qui tombent : la barre glisse vers l\'avant.',
    ],
    'leg-press' => [
        'muscles' => 'Quadriceps · fessiers',
        'tempo' => 'Descente 2 s · Poussée 1 s',
        'steps' => [
            'Dos et bassin collés au siège, pieds largeur de hanches au milieu de la plateforme.',
            'Déverrouille et descends jusqu\'à ce que les genoux forment environ 90°.',
            'Pousse dans les talons sans verrouiller les genoux en haut.',
        ],
        'mistake' => 'Descendre si bas que le bas du dos décolle du siège.',
    ],
    'goblet-squat' => [
        'muscles' => 'Quadriceps · fessiers · gainage',
        'tempo' => 'Descente 2 s · Remontée 1 s',
        'steps' => [
            'Tiens l\'haltère verticalement contre la poitrine, coudes vers le bas.',
            'Pieds un peu plus larges que les épaules.',
            'Descends entre les talons, coudes à l\'intérieur des genoux, buste droit.',
            'Remonte en poussant dans tout le pied.',
        ],
        'mistake' => 'Buste qui s\'effondre vers l\'avant.',
    ],
    'bodyweight-squat' => [
        'muscles' => 'Quadriceps · fessiers',
        'tempo' => 'Descente 2 s · Remontée 1 s',
        'steps' => [
            'Pieds largeur d\'épaules, bras tendus devant toi pour l\'équilibre.',
            'Descends en poussant les hanches en arrière, genoux dans l\'axe des pieds.',
            'Va jusqu\'aux cuisses parallèles, talons au sol.',
            'Remonte en serrant les fessiers en haut.',
        ],
        'mistake' => 'Talons qui décollent en bas.',
    ],
    // Charnière de hanche
    'deadlift' => [
        'muscles' => 'Ischio-jambiers · fessiers · dos',
        'tempo' => 'Tirage 1 s · Descente 2 s',
        'steps' => [
            'Barre au-dessus du milieu du pied, pieds largeur de hanches, mains juste à l\'extérieur des jambes.',
            'Hanches plus hautes que les genoux, dos plat, poitrine sortie, barre près des tibias.',
            'Pousse le sol avec les jambes en gardant la barre collée au corps, puis tends les hanches.',
            'Redescends en reculant d\'abord les hanches, la barre frôle les jambes.',
        ],
        'mistake' => 'Dos qui s\'arrondit, barre qui s\'éloigne des jambes.',
    ],
    'romanian-deadlift' => [
        'muscles' => 'Ischio-jambiers · fessiers',
        'tempo' => 'Descente 2 s · Remontée 1 s',
        'steps' => [
            'Debout, barre en main, genoux légèrement fléchis et fixes.',
            'Recule les hanches en gardant le dos plat, la barre glisse le long des cuisses.',
            'Descends jusqu\'à sentir l\'étirement des ischios, environ à mi-tibia.',
            'Remonte en poussant les hanches vers l\'avant et serre les fessiers.',
        ],
        'mistake' => 'Plier les genoux comme un squat ou arrondir le dos.',
    ],
    'dumbbell-romanian-deadlift' => [
        'muscles' => 'Ischio-jambiers · fessiers',
        'tempo' => 'Descente 2 s · Remontée 1 s',
        'steps' => [
            'Debout, un haltère dans chaque main devant les cuisses, genoux légèrement fléchis.',
            'Recule les hanches, dos plat, haltères le long des jambes.',
            'Descends jusqu\'à l\'étirement des ischios.',
            'Remonte en poussant les hanches vers l\'avant.',
        ],
        'mistake' => 'Haltères qui s\'éloignent des jambes.',
    ],
    'hip-thrust' => [
        'muscles' => 'Fessiers · ischio-jambiers',
        'tempo' => 'Poussée 1 s · Descente 2 s',
        'steps' => [
            'Haut du dos contre le bord du banc, barre (avec protection) posée sur les hanches.',
            'Pieds à plat, tibias verticaux en haut du mouvement.',
            'Pousse les hanches vers le haut jusqu\'à aligner épaules, hanches et genoux.',
            'Serre les fessiers une seconde, puis redescends en contrôle.',
        ],
        'mistake' => 'Se cambrer en haut au lieu de serrer les fessiers.',
    ],
    'glute-bridge' => [
        'muscles' => 'Fessiers · ischio-jambiers',
        'tempo' => 'Poussée 1 s · Descente 2 s',
        'steps' => [
            'Allongé sur le dos, genoux pliés, pieds à plat près des fessiers.',
            'Pousse dans les talons pour monter les hanches.',
            'En haut, épaules, hanches et genoux sont alignés : serre les fessiers.',
            'Redescends lentement.',
        ],
        'mistake' => 'Pousser avec le bas du dos plutôt qu\'avec les fessiers.',
    ],
    // Fentes
    'walking-lunge' => [
        'muscles' => 'Quadriceps · fessiers',
        'tempo' => 'Descente 2 s · Remontée 1 s',
        'steps' => [
            'Debout, un haltère dans chaque main, buste droit.',
            'Fais un grand pas en avant et descends jusqu\'à ce que le genou arrière frôle le sol.',
            'Pousse sur le pied avant pour te relever et enchaîne avec l\'autre jambe.',
        ],
        'mistake' => 'Pas trop court : le genou avant dépasse largement les orteils.',
    ],
    'bulgarian-split-squat' => [
        'muscles' => 'Quadriceps · fessiers',
        'tempo' => 'Descente 2 s · Remontée 1 s',
        'steps' => [
            'Dos du pied arrière posé sur un banc, pied avant à un grand pas devant.',
            'Haltères en main, buste droit ou très légèrement penché.',
            'Descends jusqu\'à ce que la cuisse avant soit parallèle au sol.',
            'Remonte en poussant sur le pied avant.',
        ],
        'mistake' => 'Pied avant trop près du banc : le genou part loin devant.',
    ],
    'step-up' => [
        'muscles' => 'Quadriceps · fessiers',
        'tempo' => 'Montée 1 s · Descente 2 s',
        'steps' => [
            'Face à un banc stable, pose un pied entier dessus.',
            'Monte en poussant sur la jambe du dessus, sans t\'aider de celle du bas.',
            'Redescends lentement en contrôle.',
        ],
        'mistake' => 'Pousser avec la jambe restée au sol.',
    ],
    'bodyweight-lunge' => [
        'muscles' => 'Quadriceps · fessiers',
        'tempo' => 'Descente 2 s · Remontée 1 s',
        'steps' => [
            'Debout, mains sur les hanches.',
            'Fais un grand pas en avant et descends jusqu\'à ce que le genou arrière frôle le sol.',
            'Pousse sur le pied avant pour revenir, puis change de jambe.',
        ],
        'mistake' => 'Genou avant qui rentre vers l\'intérieur.',
    ],
    // Isolation pectoraux
    'dumbbell-fly' => [
        'muscles' => 'Pectoraux',
        'tempo' => 'Ouverture 2 s · Fermeture 1 s',
        'steps' => [
            'Allongé, haltères au-dessus de la poitrine, paumes face à face.',
            'Coudes légèrement fléchis et fixes.',
            'Ouvre les bras en arc de cercle jusqu\'à sentir l\'étirement des pectoraux.',
            'Referme comme pour enlacer un tronc d\'arbre.',
        ],
        'mistake' => 'Descendre trop bas avec des haltères trop lourds.',
    ],
    'cable-fly' => [
        'muscles' => 'Pectoraux',
        'tempo' => 'Fermeture 1 s · Retour 2 s',
        'steps' => [
            'Poulies réglées en haut, une poignée dans chaque main, un pied en avant.',
            'Coudes légèrement fléchis et fixes.',
            'Ramène les mains devant toi vers le bas du ventre.',
            'Reviens lentement jusqu\'à l\'étirement.',
        ],
        'mistake' => 'Plier les coudes pour pousser au lieu de rapprocher les bras.',
    ],
    'pec-deck' => [
        'muscles' => 'Pectoraux',
        'tempo' => 'Fermeture 1 s · Retour 2 s',
        'steps' => [
            'Règle le siège pour que les poignées soient à hauteur de poitrine.',
            'Dos collé au dossier, coudes légèrement fléchis.',
            'Rapproche les bras devant toi et serre une seconde.',
            'Reviens lentement sans laisser la charge cogner.',
        ],
        'mistake' => 'Laisser les bras partir loin derrière en fin de retour.',
    ],
    // Épaules
    'lateral-raise' => [
        'muscles' => 'Épaules (deltoïdes latéraux)',
        'tempo' => 'Montée 1 s · Descente 2 s',
        'steps' => [
            'Debout, haltères le long du corps, coudes légèrement fléchis.',
            'Monte les bras sur les côtés jusqu\'à l\'horizontale.',
            'Les coudes mènent le mouvement, les pouces restent un peu vers le bas.',
            'Redescends lentement.',
        ],
        'mistake' => 'Hausser les épaules ou lancer les haltères avec le corps.',
    ],
    'rear-delt-fly' => [
        'muscles' => 'Arrière des épaules · haut du dos',
        'tempo' => 'Montée 1 s · Descente 2 s',
        'steps' => [
            'Buste penché vers l\'avant, dos plat, haltères sous la poitrine.',
            'Coudes légèrement fléchis.',
            'Écarte les bras sur les côtés jusqu\'à l\'horizontale.',
            'Redescends en contrôle.',
        ],
        'mistake' => 'Se redresser pour monter les haltères.',
    ],
    'face-pull' => [
        'muscles' => 'Arrière des épaules · haut du dos',
        'tempo' => 'Tirage 1 s · Retour 2 s',
        'steps' => [
            'Poulie à hauteur du visage, corde tenue avec les pouces vers toi.',
            'Recule d\'un pas, buste droit.',
            'Tire la corde vers le front en écartant les mains, coudes hauts.',
            'Reviens lentement bras tendus.',
        ],
        'mistake' => 'Coudes qui descendent : le mouvement devient un rowing.',
    ],
    'cable-lateral-raise' => [
        'muscles' => 'Épaules (deltoïdes latéraux)',
        'tempo' => 'Montée 1 s · Descente 2 s',
        'steps' => [
            'Poulie basse sur le côté, poignée tenue avec la main opposée.',
            'Monte le bras sur le côté jusqu\'à l\'horizontale, coude légèrement fléchi.',
            'Redescends lentement en gardant la tension.',
        ],
        'mistake' => 'Pencher le buste pour aider le bras.',
    ],
    // Biceps
    'barbell-curl' => [
        'muscles' => 'Biceps',
        'tempo' => 'Montée 1 s · Descente 2 s',
        'steps' => [
            'Debout, barre en supination (paumes vers l\'avant), mains largeur d\'épaules.',
            'Coudes collés au corps, fixes.',
            'Monte la barre vers les épaules en pliant les coudes.',
            'Redescends lentement jusqu\'aux bras tendus.',
        ],
        'mistake' => 'Balancer le buste pour monter la barre.',
    ],
    'dumbbell-curl' => [
        'muscles' => 'Biceps',
        'tempo' => 'Montée 1 s · Descente 2 s',
        'steps' => [
            'Debout, haltères le long du corps, paumes vers l\'avant.',
            'Coudes collés au corps.',
            'Monte les haltères vers les épaules.',
            'Redescends lentement jusqu\'aux bras tendus.',
        ],
        'mistake' => 'Coudes qui avancent pendant la montée.',
    ],
    'hammer-curl' => [
        'muscles' => 'Biceps · avant-bras',
        'tempo' => 'Montée 1 s · Descente 2 s',
        'steps' => [
            'Debout, haltères le long du corps, paumes face à face.',
            'Coudes collés au corps.',
            'Monte les haltères en gardant les paumes face à face.',
            'Redescends lentement.',
        ],
        'mistake' => 'Monter avec un élan des épaules.',
    ],
    'cable-curl' => [
        'muscles' => 'Biceps',
        'tempo' => 'Montée 1 s · Descente 2 s',
        'steps' => [
            'Face à la poulie basse, barre ou poignée en supination.',
            'Coudes collés au corps.',
            'Monte vers les épaules et serre une seconde.',
            'Redescends lentement en gardant la tension.',
        ],
        'mistake' => 'Reculer les épaules pour tirer.',
    ],
    // Triceps
    'triceps-pushdown' => [
        'muscles' => 'Triceps',
        'tempo' => 'Extension 1 s · Retour 2 s',
        'steps' => [
            'Face à la poulie haute, barre ou corde en main, coudes collés au corps.',
            'Pousse vers le bas jusqu\'aux bras tendus.',
            'Remonte lentement jusqu\'à ce que les avant-bras soient à l\'horizontale.',
        ],
        'mistake' => 'Coudes qui bougent et épaules qui poussent.',
    ],
    'overhead-dumbbell-extension' => [
        'muscles' => 'Triceps',
        'tempo' => 'Descente 2 s · Extension 1 s',
        'steps' => [
            'Debout ou assis, un haltère tenu à deux mains au-dessus de la tête.',
            'Coudes pointés vers le haut, près de la tête.',
            'Descends l\'haltère derrière la tête en pliant seulement les coudes.',
            'Remonte jusqu\'aux bras tendus.',
        ],
        'mistake' => 'Coudes qui s\'écartent largement sur les côtés.',
    ],
    'skull-crusher' => [
        'muscles' => 'Triceps',
        'tempo' => 'Descente 2 s · Extension 1 s',
        'steps' => [
            'Allongé, barre bras tendus au-dessus des épaules.',
            'Bras légèrement inclinés vers la tête, fixes.',
            'Plie les coudes pour descendre la barre vers le front.',
            'Tends les bras pour remonter.',
        ],
        'mistake' => 'Bouger les épaules : l\'exercice devient un développé.',
    ],
    'diamond-push-up' => [
        'muscles' => 'Triceps · pectoraux',
        'tempo' => 'Descente 2 s · Poussée 1 s',
        'steps' => [
            'Mains rapprochées sous la poitrine, pouces et index forment un losange.',
            'Corps gainé et aligné des talons à la tête.',
            'Descends la poitrine vers les mains, coudes près du corps.',
            'Pousse pour revenir bras tendus.',
        ],
        'mistake' => 'Bassin qui s\'affaisse.',
    ],
    'bench-dips' => [
        'muscles' => 'Triceps',
        'tempo' => 'Descente 2 s · Poussée 1 s',
        'steps' => [
            'Mains sur le bord d\'un banc derrière toi, doigts vers l\'avant.',
            'Jambes tendues devant toi, fesses près du banc.',
            'Descends en pliant les coudes vers l\'arrière jusqu\'à environ 90°.',
            'Pousse pour revenir bras tendus.',
        ],
        'mistake' => 'Descendre trop bas, ce qui force sur l\'avant des épaules.',
    ],
    // Jambes isolation
    'leg-extension' => [
        'muscles' => 'Quadriceps',
        'tempo' => 'Extension 1 s · Retour 2 s',
        'steps' => [
            'Dos contre le dossier, genoux alignés avec l\'axe de la machine, boudin sur le bas des tibias.',
            'Tends les jambes jusqu\'en haut et serre une seconde.',
            'Redescends lentement.',
        ],
        'mistake' => 'Lancer la charge et décoller les fesses du siège.',
    ],
    'sissy-squat' => [
        'muscles' => 'Quadriceps',
        'tempo' => 'Descente 2 s · Remontée 1 s',
        'steps' => [
            'Debout, une main sur un support pour l\'équilibre.',
            'Monte sur la pointe des pieds et avance les genoux en penchant le corps en arrière.',
            'Garde une ligne droite des genoux aux épaules.',
            'Remonte en poussant les genoux vers l\'arrière.',
        ],
        'mistake' => 'Plier les hanches : le corps doit rester droit.',
    ],
    'leg-curl' => [
        'muscles' => 'Ischio-jambiers',
        'tempo' => 'Flexion 1 s · Retour 2 s',
        'steps' => [
            'Allongé sur le ventre, boudin juste au-dessus des talons.',
            'Hanches collées au banc.',
            'Ramène les talons vers les fessiers.',
            'Redescends lentement jusqu\'aux jambes presque tendues.',
        ],
        'mistake' => 'Décoller les hanches pour tirer.',
    ],
    'nordic-curl' => [
        'muscles' => 'Ischio-jambiers',
        'tempo' => 'Descente 3 s · Remontée 1 s',
        'steps' => [
            'À genoux sur un tapis, chevilles bloquées par un partenaire ou un support.',
            'Corps droit des genoux à la tête.',
            'Descends le plus lentement possible vers l\'avant en résistant avec les ischios.',
            'Rattrape-toi avec les mains, puis pousse légèrement pour remonter.',
        ],
        'mistake' => 'Plier les hanches pendant la descente.',
    ],
    // Mollets
    'standing-calf-raise' => [
        'muscles' => 'Mollets',
        'tempo' => 'Montée 1 s · Descente 2 s',
        'steps' => [
            'Épaules sous les coussins, avant des pieds sur la marche, talons dans le vide.',
            'Jambes tendues, pas verrouillées.',
            'Monte le plus haut possible sur la pointe des pieds et tiens une seconde.',
            'Redescends lentement jusqu\'à l\'étirement complet.',
        ],
        'mistake' => 'Rebondir en bas au lieu de contrôler.',
    ],
    'dumbbell-calf-raise' => [
        'muscles' => 'Mollets',
        'tempo' => 'Montée 1 s · Descente 2 s',
        'steps' => [
            'Debout, un haltère dans chaque main, avant des pieds sur une marche.',
            'Monte sur la pointe des pieds et tiens une seconde.',
            'Redescends lentement, talons sous la marche.',
        ],
        'mistake' => 'Plier les genoux pour aider.',
    ],
    'bodyweight-calf-raise' => [
        'muscles' => 'Mollets',
        'tempo' => 'Montée 1 s · Descente 2 s',
        'steps' => [
            'Debout, une main contre un mur pour l\'équilibre.',
            'Monte sur la pointe des pieds le plus haut possible et tiens une seconde.',
            'Redescends lentement.',
        ],
        'mistake' => 'Aller trop vite : c\'est la lenteur qui fait travailler.',
    ],
    // Gainage
    'crunch' => [
        'muscles' => 'Abdominaux',
        'tempo' => 'Enroulement 1 s · Retour 1,5 s',
        'steps' => [
            'Allongé sur le dos, genoux pliés, mains derrière la tête sans tirer.',
            'Enroule le haut du dos en rapprochant les côtes du bassin.',
            'Souffle en montant, tiens une seconde.',
            'Redescends lentement.',
        ],
        'mistake' => 'Tirer sur la nuque avec les mains.',
    ],
    'hanging-leg-raise' => [
        'muscles' => 'Abdominaux · fléchisseurs de hanches',
        'tempo' => 'Montée 1 s · Descente 1,5 s',
        'steps' => [
            'Suspendu à la barre, épaules basses, corps immobile.',
            'Monte les jambes devant toi jusqu\'à l\'horizontale, ou les genoux vers la poitrine pour débuter.',
            'Redescends lentement sans te balancer.',
        ],
        'mistake' => 'Prendre de l\'élan avec tout le corps.',
    ],
    'cable-crunch' => [
        'muscles' => 'Abdominaux',
        'tempo' => 'Enroulement 1 s · Retour 1,5 s',
        'steps' => [
            'À genoux face à la poulie haute, corde tenue près de la tête.',
            'Hanches fixes, enroule le buste vers le sol en rapprochant les côtes du bassin.',
            'Remonte lentement en gardant la tension.',
        ],
        'mistake' => 'Tirer avec les bras ou s\'asseoir sur les talons.',
    ],
    // Puissance et explosivité
    'push-press' => [
        'muscles' => 'Épaules · triceps · jambes',
        'tempo' => 'Impulsion explosive · Descente 2 s',
        'steps' => [
            'Debout, barre posée en haut de la poitrine, coudes légèrement devant.',
            'Fléchis légèrement les genoux, buste droit, sans t\'asseoir.',
            'Pousse le sol avec les jambes et enchaîne la poussée des bras d\'un seul geste explosif.',
            'Redescends la barre en contrôle sur la poitrine.',
        ],
        'mistake' => 'Plier les genoux trop bas ou se pencher en avant pendant l\'impulsion.',
    ],
    'clap-push-up' => [
        'muscles' => 'Pectoraux · triceps · épaules',
        'tempo' => 'Descente contrôlée · Poussée explosive',
        'steps' => [
            'Position de pompe, corps gainé.',
            'Descends en contrôle, poitrine près du sol.',
            'Pousse le sol si fort que les mains décollent, frappe dans les mains.',
            'Réceptionne-toi bras souples et enchaîne.',
        ],
        'mistake' => 'Atterrir bras tendus et verrouillés : risque pour les coudes.',
    ],
    'box-jump' => [
        'muscles' => 'Quadriceps · fessiers · mollets',
        'tempo' => 'Impulsion explosive · Réception souple',
        'steps' => [
            'Face à une box stable, pieds largeur de hanches.',
            'Fléchis rapidement hanches et genoux en lançant les bras en arrière.',
            'Saute en lançant les bras vers l\'avant et pose les deux pieds entiers sur la box.',
            'Redescends en marchant, ne saute pas en arrière.',
        ],
        'mistake' => 'Choisir une box trop haute et atterrir en squat profond.',
    ],
    'broad-jump' => [
        'muscles' => 'Fessiers · quadriceps · ischio-jambiers',
        'tempo' => 'Impulsion explosive · Réception stable',
        'steps' => [
            'Pieds largeur de hanches, bras tendus devant.',
            'Lance les bras en arrière en fléchissant les hanches.',
            'Projette-toi le plus loin possible vers l\'avant en tendant hanches et genoux.',
            'Réceptionne-toi genoux fléchis, stable, puis reviens en marchant.',
        ],
        'mistake' => 'Genoux qui rentrent vers l\'intérieur à la réception.',
    ],
    'jump-squat' => [
        'muscles' => 'Quadriceps · fessiers · mollets',
        'tempo' => 'Descente rapide · Saut maximal',
        'steps' => [
            'Pieds largeur d\'épaules, mains sur les hanches ou devant toi.',
            'Descends en quart de squat, rapidement.',
            'Saute le plus haut possible en tendant hanches, genoux et chevilles.',
            'Réceptionne-toi souplement et enchaîne.',
        ],
        'mistake' => 'Atterrir jambes tendues et talons en premier.',
    ],
];
