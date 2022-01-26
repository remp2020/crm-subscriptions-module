<?php

namespace Crm\SubscriptionsModule\Length;

use DateTime;
use Nette\Database\Table\IRow;

class CalendarDaysLengthMethod implements LengthMethodInterface
{
    public function getEndTime(DateTime $startTime, IRow $subscriptionType, bool $isExtending): Length
    {
        $length = intval(date('t', $startTime->getTimestamp()));
        $interval = new \DateInterval("P{$length}D");
        $end = (clone $startTime)->add($interval);
        return new Length($end, $length);
    }
}
