<?php

namespace App\Http\Controllers;

use App\Services\WorkoutBootData;
use Illuminate\Http\Request;

class WorkoutController extends Controller
{
    public function create(Request $request, WorkoutBootData $bootData)
    {
        return view('workout.run', ['boot' => $bootData->for($request->user())]);
    }
}
