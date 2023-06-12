<?php

namespace Crm\SubscriptionsModule\Events;

use League\Event\AbstractEvent;
use Nette\Database\Table\ActiveRow;

class ContentAccessAssignedEvent extends AbstractEvent implements SubscriptionTypeEventInterface, ContentAccessEventInterface
{
    public function __construct(
        private ActiveRow $subscriptionType,
        private ActiveRow $contentAccess,
    ) {
    }

    public function getSubscriptionType(): ActiveRow
    {
        return $this->subscriptionType;
    }

    public function getContentAccess(): ?ActiveRow
    {
        return $this->contentAccess;
    }
}
