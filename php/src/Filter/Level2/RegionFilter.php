<?php

namespace R2Z2Examples\Filter\Level2;

use R2Z2Examples\Filter\KillmailFilterInterface;

class RegionFilter implements KillmailFilterInterface
{
    /** @var string[] */
    private array $regionLabels;

    /**
     * @param int[] $regionIds Region IDs to match (checked against zkb labels like "reg:10000002")
     * @param string $mode 'include' = only keep matches, 'exclude' = reject matches
     */
    public function __construct(
        private array $regionIds,
        private string $mode = 'include',
    ) {
        $this->regionLabels = array_map(fn(int $id) => "reg:{$id}", $regionIds);
    }

    public function filter(array $killmail): bool
    {
        $labels = $killmail['zkb']['labels'] ?? [];
        $match = !empty(array_intersect($labels, $this->regionLabels));

        return $this->mode === 'include' ? $match : !$match;
    }
}
