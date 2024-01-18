<?php

namespace Crm\SubscriptionsModule\Models\Length;

use DateTime;
use Nette\Database\Table\ActiveRow;

class FixDaysLengthMethod implements LengthMethodInterface
{
    public const METHOD_CODE = 'fix_days';

    public function getEndTime(DateTime $startTime, ActiveRow $subscriptionType, bool $isExtending = false): Length
    {
        $length = $subscriptionType->length;
        if ($isExtending && $subscriptionType->extending_length) {
            $length = $subscriptionType->extending_length;
        }
        $interval = new \DateInterval("P{$length}D");
        $end = (clone $startTime)->add($interval);

        return new Length($end, $length);
    }
}
