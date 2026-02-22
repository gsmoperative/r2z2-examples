<?php

namespace R2Z2Examples\Filter\Level1;

use R2Z2Examples\Filter\KillmailFilterInterface;

class SoloFilter implements KillmailFilterInterface
{
    public function __construct(
        private bool $exclude = false,
    ) {}

    public function filter(array $killmail): bool
    {
        $isSolo = (bool) ($killmail['zkb']['solo'] ?? false);

        return $this->exclude ? !$isSolo : $isSolo;
    }
}
