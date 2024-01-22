<?php

namespace Crm\SubscriptionsModule\Models\Length;

use DateTime;
use Nette\Database\Table\ActiveRow;

class CalendarDaysLengthMethod implements LengthMethodInterface
{
    public const METHOD_CODE = 'calendar_days';

    public function getEndTime(DateTime $startTime, ActiveRow $subscriptionType, bool $isExtending): Length
    {
        $length = intval(date('t', $startTime->getTimestamp()));
        $interval = new \DateInterval("P{$length}D");
        $end = (clone $startTime)->add($interval);
        return new Length($end, $length);
    }
}
