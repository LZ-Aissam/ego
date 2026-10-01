# EGO

La musculation entre amis : séances guidées, carnet, records (et bientôt programmes générés et classement).

## Prérequis

- PHP 8.4 avec l'extension `pdo_mysql`
- MariaDB 11 (en local : WampServer, port 3307, bases `ego` et `ego_testing` en `utf8mb4_unicode_ci`)
- Composer 2
- Node 20+ (uniquement pour compiler les assets)

## Démarrer en local

WampServer doit être lancé (icône verte).

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
npm run dev          # dans un terminal
php artisan serve    # dans un autre
```

Ouvrir http://127.0.0.1:8000.

## Tests

```bash
./vendor/bin/pest    # PHP (sur la base ego_testing)
npm test             # JavaScript
```

## Mise en production (VPS ou mutualisé)

1. En local : `npm run build` (génère `public/build`).
2. Envoyer le projet sur le serveur (sans `node_modules`). Sur un mutualisé sans SSH, envoyer aussi `vendor/` après `composer install --no-dev --optimize-autoloader` en local.
3. Faire pointer le domaine sur le dossier `public/`.
4. `.env` : `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://…`, `DB_CONNECTION=mariadb` + hôte, port (3306 en général), base et identifiants.
5. `php artisan migrate --force && php artisan db:seed --force && php artisan config:cache && php artisan route:cache && php artisan view:cache`.
6. HTTPS obligatoire (service worker et installation de la PWA).

Aucun cron ni worker n'est nécessaire. Les tables utilisent le moteur InnoDB (forcé dans `config/database.php`).
