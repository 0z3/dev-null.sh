<?php

declare(strict_types=1);

namespace App\Queries;

use App\Models\User;

final readonly class UserStreakQuery
{
    public function __construct(private ActivityStreaksQuery $streaks) {}

    /**
     * @return array{longest: int, current: int}
     */
    public function get(User $user, ?User $viewer): array
    {
        return $this->streaks->get($user->commits()->visibleTo($viewer)->toBase());
    }
}
