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

    public function quest(Request $request, \App\Models\Quest $quest, WorkoutBootData $bootData)
    {
        abort_unless($quest->user_id === $request->user()->id, 403);

        if ($quest->type === 'calibration') {
            return redirect()->route('calibration.quest', $quest);
        }

        if ($quest->status !== 'pending' || ! $quest->date->isToday()) {
            return redirect()->route('home');
        }

        return view('workout.run', ['boot' => $bootData->forQuest($request->user(), $quest)]);
    }
}
