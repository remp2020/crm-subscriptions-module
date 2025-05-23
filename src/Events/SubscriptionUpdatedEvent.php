<?php

declare(strict_types=1);

namespace Crm\SubscriptionsModule\Events;

use Crm\UsersModule\Events\UserEventInterface;
use League\Event\AbstractEvent;
use Nette\Database\Table\ActiveRow;

/**
 * Event emitted in case of subscription's basic parameters update
 * (listed in SubscriptionsRepository::update - $eventTriggeringParams)
 *
 * Class SubscriptionUpdatedEvent
 * @package Crm\SubscriptionsModule\Events
 */
class SubscriptionUpdatedEvent extends AbstractEvent implements UserEventInterface, SubscriptionEventInterface
{
    public function __construct(
        private ActiveRow $subscription,
    ) {
    }

    public function getSubscription(): ActiveRow
    {
        return $this->subscription;
    }

    public function getUser(): ActiveRow
    {
        return $this->subscription->user;
    }
}
