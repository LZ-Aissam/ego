<?php

namespace App\Game;

use Illuminate\Support\Collection;

final class QuestEvaluator
{
    /** @return list<int> séries comptées pour chaque objectif */
    public function progress(array $objectives, Collection $sets): array
    {
        // Un exercice de la quête ne compte que pour son propre objectif ; un exercice hors quête
        // (remplacement) compte pour les objectifs de son emplacement.
        $objectiveIds = array_column($objectives, 'exercise_id');

        return array_map(fn (array $objective) => $sets->filter(
            fn ($set) => $set->exercise_id === $objective['exercise_id']
                || (! in_array($set->exercise_id, $objectiveIds, true) && in_array($objective['slot'], $set->exercise->slots ?? [], true))
        )->count(), $objectives);
    }

    /** @param  list<int>  $skipped  exercices passés faute de temps : l'objectif compte comme réglé, la quête n'est pas punie. */
    public function isAccomplished(array $objectives, Collection $sets, array $skipped = []): bool
    {
        if ($objectives === []) {
            return false;
        }

        foreach ($this->progress($objectives, $sets) as $i => $done) {
            if ($done < $objectives[$i]['sets'] && ! in_array($objectives[$i]['exercise_id'], $skipped, true)) {
                return false;
            }
        }

        return true;
    }
}
