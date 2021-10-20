<?php

namespace Crm\SubscriptionsModule\Length;

use DateTime;
use Nette\Database\Table\ActiveRow;

class FixDaysLengthMethod implements LengthMethodInterface
{
    public function getEndTime(DateTime $startTime, ActiveRow $subscriptionType, bool $isExtending = false): Length
    {
        $length = $subscriptionType->length;
        if ($isExtending && $subscriptionType->extending_length) {
            $length = $subscriptionType->extending_length;
        }
        $interval = new \DateInterval("P{$length}D");
        $end = (clone $startTime)->add($interval);

        if ($subscriptionType->fixed_end) {
            $end = $subscriptionType->fixed_end;
        }

        return new Length($end, $length);
    }
}
