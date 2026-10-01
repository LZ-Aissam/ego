# EGO — Livraison D « Crew » : membres, classement, groupes

## 1. Objectif et périmètre

Rendre EGO social entre amis : chaque inscrit voit les autres joueurs, se compare à eux dans un classement et peut former des groupes filtrant ce classement.

**Dans le périmètre** : liste des membres et profil public, classement (XP par période, série, force relative), groupes (créer, rejoindre par code ou lien, quitter, gestion par le créateur), ligne de classement sur l'accueil.

**Hors périmètre** : défis (livraison E), copie de programmes (livraison F), fil d'activité, messagerie, notifications sociales, tâche planifiée, mode hors ligne des pages Crew.

## 2. Décisions

| Sujet | Décision |
|---|---|
| Découpage | D = Membres + Classement + Groupes ; E = Défis ; F = Copie de programmes |
| Mesures du classement | XP (semaine, mois, depuis toujours), Série, Force relative (développé couché, squat, soulevé de terre) |
| Groupes | Création libre, code à 6 caractères, plusieurs groupes par membre, créateur avec droits de gestion |
| Profil public | Carte de joueur, records, profil de force, programme actif, groupes en commun ; rien de privé |
| Calcul | À la volée depuis les tables existantes, sans table précalculée ni tâche planifiée |
| Poids déductible | Accepté : records en kg + ratio de force permettent d'estimer le poids, acceptable entre amis |

## 3. Données

### 3.1 Table `crews`

| Colonne | Type | Règle |
|---|---|---|
| `id` | id | |
| `name` | string(30) | 2 à 30 caractères, espaces de bord retirés |
| `color` | string(10) | une de : `ice`, `cyan`, `violet`, `gold`, `red`, `green` |
| `code` | string(6), unique | 6 caractères parmi `ABCDEFGHJKLMNPQRSTUVWXYZ23456789` (ni 0, O, 1, I) |
| `owner_id` | FK users, cascade | créateur actuel |
| timestamps | | |

### 3.2 Table `crew_user`

`crew_id` (FK, cascade), `user_id` (FK, cascade), `joined_at` (datetime) ; unique (`crew_id`, `user_id`).

### 3.3 Modèles

- `Crew` : `owner()`, `members()` (belongsToMany avec `joined_at`), `isOwnedBy(User)`, `hasMember(User)`.
- `User` : `crews()`.
- Le code est généré par `Crew::generateCode()` (aléatoire, re-tirage en cas de collision).

## 4. Règles des groupes (`App\Crew\CrewService`)

- **Créer** (`create(User, name, color)`) : le créateur devient `owner_id` et membre. Refusé au-delà de **10 groupes** par membre (« Tu fais déjà partie de 10 groupes. »).
- **Rejoindre** (`join(User, code)`) : le code est normalisé (majuscules, espaces retirés). Code inconnu → « Code inconnu. ». Déjà membre → aucun changement, on arrive sur le groupe. Limite de 10 groupes appliquée. Limite de **10 tentatives par minute** et par joueur (RateLimiter), au-delà « Trop d'essais, réessaie dans une minute. ».
- **Quitter** (`leave(User, Crew)`) : un membre retire son adhésion. Si c'est le créateur, le rôle passe au membre restant le plus ancien (`joined_at`, puis `id`). S'il ne reste personne, le groupe est supprimé.
- **Créateur uniquement** : renommer, changer la couleur (`update`), régénérer le code (`regenerateCode`, l'ancien cesse de fonctionner), retirer un membre autre que lui (`kick`), supprimer le groupe (`delete`, confirmation dans l'interface, pas de boîte de dialogue du navigateur). Toute tentative d'un non-créateur → 403.
- Supprimer un groupe supprime ses adhésions ; aucune donnée de joueur (séances, XP, records) n'est touchée.

## 5. Classement (`App\Crew\Leaderboard`)

`rank(string $metric, ?string $period, ?string $lift, ?Crew $crew): Collection` renvoie des lignes `{user, value, rank}` triées.

- **Population** : tous les inscrits, ou les membres du groupe si un groupe est donné.
- **XP** :
  - `week` : somme de `xp_events.amount` depuis lundi 00:00 (Europe/Paris) ;
  - `month` : depuis le 1er du mois 00:00 ;
  - `all` : `users.xp_total`.
- **Série** : `users.streak_days`.
- **Force** (`lift` ∈ `bench` → `bench-press`, `squat` → `back-squat`, `deadlift` → `deadlift`) : `personal_records.best_e1rm_kg ÷ users.weight_kg`, arrondi à 0,01. Les membres sans poids ou sans record sur ce mouvement sont exclus.
- **Ordre** : valeur décroissante, puis prénom alphabétique. **Ex aequo** : même valeur → même rang (1, 2, 2, 4).
- XP et Série : les membres à 0 sont classés en bas avec leur rang.
- Un joueur en pause reste classé ; la ligne porte un indicateur « en pause ».

Affichage : rang, prénom, titre équipé, insigne de rang E–S, valeur (`1 240 XP`, `12 j`, `×1,35`). Podium (1–3) mis en valeur. Top 50 affiché ; ma ligne surlignée, épinglée en bas si je suis hors du top 50. Liste vide → « Aucun chasseur classé pour l'instant. ».

## 6. Écrans

Routes (groupe `auth` + `SyncQuests`) ; `/community` redirige vers `/crew`.

| Route | Nom | Contenu |
|---|---|---|
| `/crew` | `community` | Page Crew, onglets Classement (défaut) · Membres · Groupes (paramètre `?tab=`) |
| `/crew/membres/{user}` | `crew.member` | Profil public |
| `/crew/groupes/{crew}` | `crew.group` | Page d'un groupe (membres seulement, sinon 404) |
| `/crew/rejoindre/{code}` | `crew.join` | Rejoint puis redirige vers le groupe ; code inconnu → onglet Groupes avec « Code inconnu. » |

- **Classement** : sélecteurs mesure (XP / Série / Force), période (XP) ou mouvement (Force), filtre « Tout le monde » ou un de mes groupes. Les filtres sont des propriétés Livewire validées : un groupe dont je ne suis pas membre → filtre ignoré (« Tout le monde »).
- **Membres** : liste de tous les inscrits (prénom, niveau, rang, titre), recherche par prénom, lien vers le profil public.
- **Profil public** : carte de joueur (prénom, niveau, rang, titre, série, statistiques EGO), records, profil de force (1RM calibrés), programme actif (jours et exercices), groupes en commun. **Jamais** : date de naissance, poids, taille, IMC, historique des séances, quêtes ratées, pénalités. Sur la page Profil, lien « Voir comme les autres ».
- **Groupes** : mes groupes (emblème, nom, nombre de membres), formulaire « Créer un groupe » (nom + couleur), formulaire « Rejoindre avec un code ».
- **Page d'un groupe** : code et bouton « Copier » (lien d'invitation complet), membres avec niveau, lien « Classement du groupe », bouton « Quitter ». Pour le créateur : renommer, couleur, nouveau code, retirer un membre, supprimer (confirmation intégrée).
- **Accueil** : ligne « Classement de la semaine : tu es 3ᵉ » (XP, semaine, tout le monde) menant au Crew ; si je n'ai pas d'XP cette semaine : « Gagne de l'XP pour entrer au classement ».

Tous les textes sont en français, au style Système (`x-sys.*`), nommant le système « TON EGO » quand il parle.

## 7. Sécurité

- Toutes les pages Crew exigent une session.
- Page de groupe, code et liste des membres d'un groupe : membres seulement (404 sinon, pour ne pas révéler l'existence du groupe).
- Actions de créateur vérifiées côté serveur (403 pour les autres), même via un appel Livewire forgé.
- Propriétés Livewire sensibles (`#[Locked]`) : identifiant du groupe affiché, identifiant du membre affiché.
- Limitation des essais de code (§4).
- Le profil public ne charge ni n'affiche aucun champ privé ; un test le vérifie.

## 8. Tests (Pest, MariaDB `ego_testing`)

- **Classement** : bornes de semaine et de mois (une XP du dimanche 23:30 n'entre pas dans la semaine du lundi), ex aequo, exclusion sans poids ou sans record, filtre de groupe, joueur en pause.
- **Groupes** : créer, rejoindre (minuscules, espaces), déjà membre, code inconnu, limite de 10 groupes, limite d'essais, quitter, transfert du rôle de créateur, suppression au départ du dernier, régénération du code, retrait d'un membre, suppression.
- **Accès** : 404 non-membre, 403 actions de créateur, filtre de groupe étranger ignoré, champs privés absents du profil public.
- **Écrans** : chaque onglet s'affiche, lien d'invitation, redirection `/community`, ligne de l'accueil.
