# EGO — Livraison E « Défis entre amis »

## 1. Objectif et périmètre

Permettre aux membres d'un groupe Crew de se lancer des défis limités dans le temps, avec un classement en direct, un enjeu écrit librement et un classement final figé.

**Dans le périmètre** : création d'un défi de groupe, 4 types de score, 4 durées, participation, classement en direct et final, enjeu facultatif, messages de TON EGO au début et à la fin, onglet Défis du Crew, page d'un défi, ligne sur l'accueil.

**Hors périmètre** : défis ouverts à tous les inscrits, récompenses en XP ou en titre, notifications push, tâche planifiée, modification d'un défi après création.

## 2. Décisions

| Sujet | Décision |
|---|---|
| Types | XP gagnée, nombre de séances, volume soulevé, progression de force (%) sur développé couché / squat / soulevé de terre |
| Portée | Défi de groupe uniquement ; seuls les membres du groupe le voient et le rejoignent |
| Durées | 3 jours, 1 semaine, 2 semaines, 1 mois ; départ le lendemain à 00:00 (Europe/Paris) |
| Récompense | Aucune XP ni titre : enjeu écrit facultatif + classement final figé |
| Calcul | À la volée depuis les tables existantes ; clôture déclenchée par la navigation (comme les quêtes), sans tâche planifiée |

## 3. Données

### 3.1 Table `challenges`

| Colonne | Type | Règle |
|---|---|---|
| `id` | id | |
| `crew_id` | FK crews, cascade | groupe du défi |
| `creator_id` | FK users, cascade | créateur |
| `metric` | string(10) | `xp`, `sessions`, `volume`, `strength` |
| `lift` | string(10), nullable | `bench`, `squat`, `deadlift` si `metric = strength`, sinon null |
| `duration` | string(4) | `3d`, `1w`, `2w`, `1m` |
| `starts_at` | datetime | lendemain de la création, 00:00 |
| `ends_at` | datetime | `starts_at` + durée (exclu) : +3 jours, +7 jours, +14 jours, +1 mois |
| `stake` | string(100), nullable | enjeu facultatif, espaces de bord retirés |
| `status` | string(10) | `upcoming`, `active`, `finished`, `cancelled` |
| `final_standings` | json, nullable | classement figé à la clôture : liste de `{user_id, first_name, value, rank}` |
| `started_notified_at`, `finished_notified_at` | datetime, nullable | garantissent un seul message de début et de fin |
| timestamps | | |

### 3.2 Table `challenge_user`

`challenge_id` (FK, cascade), `user_id` (FK, cascade), `joined_at` (datetime) ; unique (`challenge_id`, `user_id`).

## 4. Règles (`App\Crew\ChallengeService`)

- **Créer** (`create(User, Crew, metric, ?lift, duration, ?stake)`) : le joueur doit être membre du groupe (sinon 404). `lift` est obligatoire pour `strength` et ignoré sinon. Au plus **3 défis à venir ou en cours** par groupe (« Ce groupe a déjà 3 défis en cours. »). Le créateur participe d'office.
- **Rejoindre** (`join`) : membre du groupe, défi non terminé ni annulé ; déjà participant → aucun changement.
- **Quitter** (`leave`) : seulement avant le début (`status = upcoming`) ; le créateur ne quitte pas son défi (il l'annule).
- **Annuler** (`cancel`) : créateur seulement, avant le début → `cancelled`. Toute autre tentative → 403.
- **Quitter le groupe** retire le joueur des défis de ce groupe qui ne sont pas terminés.
- Messages d'erreur en français ; refus de droits en 403 ; défi d'un groupe dont on n'est pas membre en 404.

## 5. Scores (`App\Crew\ChallengeBoard`)

`standings(Challenge): Collection<{user, value, rank}>`, période `[starts_at, ends_at)` :

- **xp** : somme de `xp_events.amount` dont `created_at` est dans la période.
- **sessions** : nombre de séances dont `finished_at` est dans la période.
- **volume** : somme du volume des séances terminées dans la période (même calcul que `Workout::volume()`).
- **strength** : meilleur 1RM estimé (Epley, charge effective) du mouvement sur les séances de la période, comparé au meilleur 1RM estimé **avant** `starts_at` ; valeur = gain en % arrondi à l'entier. Sans référence avant le début ou sans séance du mouvement pendant le défi : non classé (« Fais ce mouvement avant le début pour être classé »).
- Ordre : valeur décroissante, puis prénom sans accents ni majuscules ; ex aequo → même rang (1, 2, 2, 4).
- Valeurs affichées : `1 240 XP`, `4 séances`, `8 450 kg`, `+6 %` (espaces insécables).

## 6. Cycle de vie (`App\Crew\ChallengeClock`)

Appelé à chaque requête GET authentifiée (middleware des quêtes), pour les défis du joueur :

- `upcoming` et `starts_at` ≤ maintenant → `active` ; si `started_notified_at` est vide : message **« DÉFI LANCÉ »** à chaque participant, puis horodatage.
- `active` et `ends_at` ≤ maintenant → calcul du classement, enregistré dans `final_standings`, `finished` ; si `finished_notified_at` est vide : message **« DÉFI TERMINÉ »** à chaque participant (« Tu finis 2ᵉ / 5 · Vainqueur : Renée », plus « Enjeu : … » s'il existe), puis horodatage.
- Verrou sur la ligne du défi (`lockForUpdate`) pour qu'une double requête n'envoie pas deux messages.
- Après `finished`, le classement affiché est `final_standings` : les séances synchronisées plus tard n'y changent rien.

## 7. Écrans

- **Crew → onglet « Défis »** (`?tab=defis`) : sections En cours (type, groupe, temps restant « encore 3 j 4 h », mon rang « 2ᵉ / 5 », enjeu), À venir (bouton Rejoindre), Terminés (10 derniers, vainqueur), bouton **Lancer un défi**. Seuls les défis de mes groupes apparaissent.
- **Lancer un défi** : groupe (parmi les miens), type, mouvement si force, durée, enjeu facultatif ; résumé « Défi XP · 1 semaine · du lundi 12 au dimanche 18 octobre ».
- **Page d'un défi** `/crew/defis/{challenge}` (`crew.challenge`) : classement en direct (ou podium final figé), ma ligne surlignée, enjeu, boutons Rejoindre / Quitter (avant le début) / Annuler (créateur, avant le début). Non-membre du groupe → 404.
- **Accueil** : une ligne par défi en cours auquel je participe (« Défi XP · tu es 2ᵉ · encore 3 j »), lien vers le défi.

Textes en français, style Système (`x-sys.*`), TON EGO quand le système parle. L'enjeu est affiché comme texte échappé.

## 8. Tests (Pest, MariaDB `ego_testing`)

- **Scores** : 4 types bornés à la période (une séance la veille du début ne compte pas), force sans référence → non classé, ex aequo.
- **Cycle de vie** : création (départ le lendemain 00:00, fin selon la durée), rejoindre en cours, quitter seulement avant le début, annulation par le créateur seulement, limite de 3 par groupe.
- **Clôture** : classement figé, messages de début et de fin envoyés une seule fois chacun, séance synchronisée après la clôture sans effet.
- **Accès** : 404 non-membre, 403 actions forgées, quitter le groupe retire des défis non terminés.
- **Écrans** : onglet Défis, formulaire, page d'un défi, ligne de l'accueil, enjeu échappé.
