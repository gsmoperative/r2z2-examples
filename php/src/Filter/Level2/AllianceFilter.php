<?php

namespace R2Z2Examples\Filter\Level2;

use R2Z2Examples\Filter\KillmailFilterInterface;

class AllianceFilter implements KillmailFilterInterface
{
    /**
     * @param int[] $allianceIds Alliance IDs to match (victim or any attacker)
     * @param string $mode 'include' = only keep matches, 'exclude' = reject matches
     */
    public function __construct(
        private array $allianceIds,
        private string $mode = 'include',
    ) {}

    public function filter(array $killmail): bool
    {
        $match = $this->matchesAny($killmail);

        return $this->mode === 'include' ? $match : !$match;
    }

    private function matchesAny(array $killmail): bool
    {
        $victimAllianceId = $killmail['esi']['victim']['alliance_id'] ?? null;
        if ($victimAllianceId !== null && in_array($victimAllianceId, $this->allianceIds, true)) {
            return true;
        }

        foreach ($killmail['esi']['attackers'] ?? [] as $attacker) {
            if (in_array($attacker['alliance_id'] ?? null, $this->allianceIds, true)) {
                return true;
            }
        }

        return false;
    }
}
