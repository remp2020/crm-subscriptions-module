<?php

declare(strict_types=1);

namespace Crm\SubscriptionsModule\Events;

use Crm\UsersModule\Events\UserEventInterface;
use League\Event\AbstractEvent;
use Nette\Database\Table\ActiveRow;

class SubscriptionStartsEvent extends AbstractEvent implements SubscriptionEventInterface, UserEventInterface
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
