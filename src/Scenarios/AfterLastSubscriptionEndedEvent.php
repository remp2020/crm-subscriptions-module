<?php
declare(strict_types=1);

namespace Crm\SubscriptionsModule\Scenarios;

use Crm\SubscriptionsModule\Events\SubscriptionEventInterface;
use Crm\SubscriptionsModule\Events\SubscriptionTypeEventInterface;
use Crm\UsersModule\Events\UserEventInterface;
use League\Event\AbstractEvent;
use Nette\Database\Table\ActiveRow;

class AfterLastSubscriptionEndedEvent extends AbstractEvent implements UserEventInterface, SubscriptionEventInterface, SubscriptionTypeEventInterface
{
    public function __construct(
        private ActiveRow $user,
        private ActiveRow $subscription,
        private ActiveRow $subscriptionType,
    ) {
    }

    public function getUser(): ActiveRow
    {
        return $this->user;
    }

    public function getSubscription(): ActiveRow
    {
        return $this->subscription;
    }

    public function getSubscriptionType(): ActiveRow
    {
        return $this->subscriptionType;
    }
}
