<?php

namespace App\Game;

/** Ressenti d'une série lourde, donné pendant le repos : il règle la hausse de charge. */
final class Effort
{
    public const EASY = 'easy';

    public const HARD = 'hard';

    public const LIMIT = 'limit';

    public const KEYS = [self::EASY, self::HARD, self::LIMIT];
}
