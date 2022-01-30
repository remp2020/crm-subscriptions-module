<?php

namespace Crm\SubscriptionsModule\Events;

use League\Event\AbstractEvent;
use Nette\Database\Table\ActiveRow;

class SubscriptionMovedEvent extends AbstractEvent
{
    private $subscription;

    private $originalStartTime;

    private $originalEndTime;

    public function __construct(ActiveRow $subscription, \DateTime $originalStartTime, \DateTime $originalEndTime)
    {
        $this->subscription = $subscription;
        $this->originalStartTime = clone $originalStartTime;
        $this->originalEndTime = clone $originalEndTime;
    }

    public function getSubscription(): ActiveRow
    {
        return $this->subscription;
    }

    public function getOriginalStartTime(): \DateTime
    {
        return $this->originalStartTime;
    }

    public function getOriginalEndTime(): \DateTime
    {
        return $this->originalEndTime;
    }
}
