<?php

namespace App\Http\Controllers;

use App\Crew\CrewService;
use Illuminate\Validation\ValidationException;

class CrewJoinController extends Controller
{
    public function __invoke(string $code, CrewService $crews)
    {
        try {
            $crew = $crews->join(auth()->user(), $code);
        } catch (ValidationException $e) {
            return redirect()->route('community', ['tab' => 'groupes'])->with('crew_error', collect($e->errors())->flatten()->first());
        }

        return redirect()->route('crew.group', $crew);
    }
}
