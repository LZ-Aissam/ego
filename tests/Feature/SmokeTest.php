<?php

use Illuminate\Support\Facades\DB;

it('répond sur la route de santé', function () {
    $this->get('/up')->assertOk();
});

it('utilise le français et le fuseau de Paris', function () {
    expect(app()->getLocale())->toBe('fr')
        ->and(config('app.timezone'))->toBe('Europe/Paris');
});

it('teste sur la base MariaDB dédiée', function () {
    expect(DB::connection()->getDriverName())->toBe('mariadb')
        ->and(DB::connection()->getDatabaseName())->toBe('ego_testing');
});
