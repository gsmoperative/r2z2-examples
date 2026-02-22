<?php

namespace R2Z2Examples\Filter\Level1;

use R2Z2Examples\Filter\KillmailFilterInterface;

class SecurityFilter implements KillmailFilterInterface
{
    /** @var string[] */
    private array $allow;

    /**
     * @param string[] $allow Allowed security zones: 'highsec', 'lowsec', 'nullsec', 'wspace'
     */
    public function __construct(array $allow)
    {
        $this->allow = array_map(fn(string $z) => 'loc:' . ltrim($z, 'loc:'), $allow);
    }

    public function filter(array $killmail): bool
    {
        $labels = $killmail['zkb']['labels'] ?? [];

        foreach ($labels as $label) {
            if (in_array($label, $this->allow, true)) {
                return true;
            }
        }

        return false;
    }
}
