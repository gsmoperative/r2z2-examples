<?php

namespace R2Z2Examples\Filter\Level2;

use R2Z2Examples\Filter\KillmailFilterInterface;

class CharacterFilter implements KillmailFilterInterface
{
    /**
     * @param int[] $characterIds Character IDs to match (victim or any attacker)
     * @param string $mode 'include' = only keep matches, 'exclude' = reject matches
     */
    public function __construct(
        private array $characterIds,
        private string $mode = 'include',
    ) {}

    public function filter(array $killmail): bool
    {
        $match = $this->matchesAny($killmail);

        return $this->mode === 'include' ? $match : !$match;
    }

    private function matchesAny(array $killmail): bool
    {
        $victimCharId = $killmail['esi']['victim']['character_id'] ?? null;
        if ($victimCharId !== null && in_array($victimCharId, $this->characterIds, true)) {
            return true;
        }

        foreach ($killmail['esi']['attackers'] ?? [] as $attacker) {
            if (in_array($attacker['character_id'] ?? null, $this->characterIds, true)) {
                return true;
            }
        }

        return false;
    }
}
