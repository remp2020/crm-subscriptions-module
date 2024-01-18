<?php

namespace Crm\SubscriptionsModule\Models\Length;

use DateTime;

class Length
{
    private $endTime;

    private $length;

    public function __construct(DateTime $endTime, int $length)
    {
        $this->endTime = $endTime;
        $this->length = $length;
    }

    public function getEndTime(): DateTime
    {
        return $this->endTime;
    }

    public function getLength(): int
    {
        return $this->length;
    }
}
