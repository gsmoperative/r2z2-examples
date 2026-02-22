<?php

namespace R2Z2Examples\Filter\Level1;

use R2Z2Examples\Filter\KillmailFilterInterface;

class AwoxFilter implements KillmailFilterInterface
{
    public function __construct(
        private bool $exclude = false,
    ) {}

    public function filter(array $killmail): bool
    {
        $isAwox = (bool) ($killmail['zkb']['awox'] ?? false);

        return $this->exclude ? !$isAwox : $isAwox;
    }
}
