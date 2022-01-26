<?php

namespace Crm\SubscriptionsModule\Length;

use DateTime;
use Nette\Database\Table\IRow;

interface LengthMethodInterface
{
    public function getEndTime(DateTime $startTime, IRow $subscriptionType, bool $isExtending): Length;
}
