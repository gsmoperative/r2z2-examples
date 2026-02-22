<?php

namespace R2Z2Examples\Filter;

interface KillmailFilterInterface
{
    /**
     * @return bool true = keep, false = reject
     */
    public function filter(array $killmail): bool;
}
