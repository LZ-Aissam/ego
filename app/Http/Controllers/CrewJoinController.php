<?php

namespace App\Http\Controllers;

class CrewJoinController extends Controller
{
    public function __invoke(string $code)
    {
        return redirect()->route('community', ['tab' => 'groupes']);
    }
}
