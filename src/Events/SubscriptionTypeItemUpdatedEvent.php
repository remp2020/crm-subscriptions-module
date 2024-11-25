<?php

namespace Crm\SubscriptionsModule\Events;

use League\Event\AbstractEvent;
use Nette\Database\Table\ActiveRow;

class SubscriptionTypeItemUpdatedEvent extends AbstractEvent implements SubscriptionTypeEventInterface, SubscriptionTypeItemEventInterface
{
    public function __construct(private ActiveRow $subscriptionTypeItem)
    {
    }

    public function getSubscriptionTypeItem(): ?ActiveRow
    {
        return $this->subscriptionTypeItem;
    }

    public function getSubscriptionType(): ActiveRow
    {
        return $this->subscriptionTypeItem->subscription_type;
    }
}
