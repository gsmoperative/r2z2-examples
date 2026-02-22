<?php

namespace R2Z2Examples\Filter\Level2;

use R2Z2Examples\Filter\KillmailFilterInterface;

class CorporationFilter implements KillmailFilterInterface
{
    /**
     * @param int[] $corporationIds Corporation IDs to match (victim or any attacker)
     * @param string $mode 'include' = only keep matches, 'exclude' = reject matches
     */
    public function __construct(
        private array $corporationIds,
        private string $mode = 'include',
    ) {}

    public function filter(array $killmail): bool
    {
        $match = $this->matchesAny($killmail);

        return $this->mode === 'include' ? $match : !$match;
    }

    private function matchesAny(array $killmail): bool
    {
        $victimCorpId = $killmail['esi']['victim']['corporation_id'] ?? null;
        if ($victimCorpId !== null && in_array($victimCorpId, $this->corporationIds, true)) {
            return true;
        }

        foreach ($killmail['esi']['attackers'] ?? [] as $attacker) {
            if (in_array($attacker['corporation_id'] ?? null, $this->corporationIds, true)) {
                return true;
            }
        }

        return false;
    }
}
