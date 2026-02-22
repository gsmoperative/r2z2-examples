<?php

namespace R2Z2Examples\Filter;

class FilterPipeline
{
    /** @var KillmailFilterInterface[] */
    private array $level1 = [];

    /** @var KillmailFilterInterface[] */
    private array $level2 = [];

    public function addLevel1(KillmailFilterInterface $filter): self
    {
        $this->level1[] = $filter;
        return $this;
    }

    public function addLevel2(KillmailFilterInterface $filter): self
    {
        $this->level2[] = $filter;
        return $this;
    }

    /**
     * Run the filter pipeline. Level 1 filters run first - if any rejects,
     * Level 2 is skipped entirely. Then all Level 2 filters must pass.
     *
     * @return bool true = keep, false = reject
     */
    public function evaluate(array $killmail): bool
    {
        foreach ($this->level1 as $filter) {
            if (!$filter->filter($killmail)) {
                return false;
            }
        }

        foreach ($this->level2 as $filter) {
            if (!$filter->filter($killmail)) {
                return false;
            }
        }

        return true;
    }
}
