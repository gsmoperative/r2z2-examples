<?php

namespace R2Z2Examples\Filter\Level2;

use R2Z2Examples\Filter\KillmailFilterInterface;

class SolarSystemFilter implements KillmailFilterInterface
{
    /**
     * @param int[] $systemIds Solar system IDs to match
     * @param string $mode 'include' = only keep matches, 'exclude' = reject matches
     */
    public function __construct(
        private array $systemIds,
        private string $mode = 'include',
    ) {}

    public function filter(array $killmail): bool
    {
        $systemId = $killmail['esi']['solar_system_id'] ?? null;
        $match = in_array($systemId, $this->systemIds, true);

        return $this->mode === 'include' ? $match : !$match;
    }
}
