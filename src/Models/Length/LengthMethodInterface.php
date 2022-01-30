<?php

namespace Crm\SubscriptionsModule\Length;

use DateTime;
use Nette\Database\Table\ActiveRow;

interface LengthMethodInterface
{
    public function getEndTime(DateTime $startTime, ActiveRow $subscriptionType, bool $isExtending): Length;
}
