<?php

namespace Crm\SubscriptionsModule\Events;

use League\Event\AbstractEvent;
use Nette\Database\Table\ActiveRow;

class SubscriptionTypeTagAddedEvent extends AbstractEvent implements SubscriptionTypeEventInterface
{
    public function __construct(private ActiveRow $subscriptionType, private string $tag)
    {
    }

    public function getSubscriptionType(): ActiveRow
    {
        return $this->subscriptionType;
    }

    public function getTag(): string
    {
        return $this->tag;
    }
}
