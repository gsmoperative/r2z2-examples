<?php

namespace R2Z2Examples\Filter\Level2;

use R2Z2Examples\Filter\KillmailFilterInterface;

class ShipTypeFilter implements KillmailFilterInterface
{
    /**
     * @param int[] $typeIds Ship type IDs to match
     * @param string $mode 'include' = only keep matches, 'exclude' = reject matches
     */
    public function __construct(
        private array $typeIds,
        private string $mode = 'include',
    ) {}

    public function filter(array $killmail): bool
    {
        $shipTypeId = $killmail['esi']['victim']['ship_type_id'] ?? null;
        $match = in_array($shipTypeId, $this->typeIds, true);

        return $this->mode === 'include' ? $match : !$match;
    }
}
