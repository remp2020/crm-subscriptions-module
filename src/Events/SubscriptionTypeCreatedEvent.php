<?php

namespace Crm\SubscriptionsModule\Events;

use League\Event\AbstractEvent;
use Nette\Database\Table\ActiveRow;

class SubscriptionTypeCreatedEvent extends AbstractEvent implements SubscriptionTypeEventInterface
{
    public function __construct(private ActiveRow $subscriptionType)
    {
    }

    public function getSubscriptionType(): ActiveRow
    {
        return $this->subscriptionType;
    }
}
