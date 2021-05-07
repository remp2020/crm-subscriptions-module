<?php

namespace Crm\SubscriptionsModule\Events;

use League\Event\AbstractEvent;
use Nette\Database\Table\ActiveRow;

class SubscriptionShortenedEvent extends AbstractEvent
{
    private $baseSubscription;

    private $originalEndTime;

    public function __construct(ActiveRow $baseSubscription, \DateTime $originalEndTime)
    {
        $this->baseSubscription = $baseSubscription;
        $this->originalEndTime = clone $originalEndTime;
    }

    public function getBaseSubscription(): ActiveRow
    {
        return $this->baseSubscription;
    }

    public function getOriginalEndTime(): \DateTime
    {
        return $this->originalEndTime;
    }
}
