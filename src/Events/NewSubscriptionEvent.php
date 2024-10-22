<?php

declare(strict_types=1);

namespace Crm\SubscriptionsModule\Events;

use Crm\UsersModule\Events\UserEventInterface;
use League\Event\AbstractEvent;
use Nette\Database\Table\ActiveRow;

class NewSubscriptionEvent extends AbstractEvent implements UserEventInterface, SubscriptionEventInterface
{
    public function __construct(
        private ActiveRow $subscription,
        private bool $sendEmail = true,
    ) {
    }

    public function getSubscription(): ActiveRow
    {
        return $this->subscription;
    }

    public function getSendEmail()
    {
        return $this->sendEmail;
    }

    public function getUser(): ActiveRow
    {
        return $this->subscription->user;
    }
}
