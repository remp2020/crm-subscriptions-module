<?php

namespace Crm\SubscriptionsModule\Models\Extension;

class Extension
{
    private \DateTime $date;

    private bool $isExtending;

    public function __construct(\DateTime $date, bool $isExtending = false)
    {
        $this->date = $date;
        $this->isExtending = $isExtending;
    }

    public function getDate(): \DateTime
    {
        return $this->date;
    }

    public function isExtending(): bool
    {
        return $this->isExtending;
    }
}
