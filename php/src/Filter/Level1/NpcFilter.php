<?php

namespace R2Z2Examples\Filter\Level1;

use R2Z2Examples\Filter\KillmailFilterInterface;

class NpcFilter implements KillmailFilterInterface
{
    public function __construct(
        private bool $exclude = false,
    ) {}

    public function filter(array $killmail): bool
    {
        $isNpc = (bool) ($killmail['zkb']['npc'] ?? false);

        return $this->exclude ? !$isNpc : $isNpc;
    }
}
