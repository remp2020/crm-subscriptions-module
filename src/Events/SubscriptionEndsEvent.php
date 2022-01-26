<?php

namespace Crm\SubscriptionsModule\Events;

use Crm\UsersModule\User\ISubscriptionGetter;
use Crm\UsersModule\User\IUserGetter;
use League\Event\AbstractEvent;
use Nette\Database\Table\ActiveRow;
use Nette\Database\Table\IRow;

class SubscriptionEndsEvent extends AbstractEvent implements ISubscriptionGetter, IUserGetter
{
    /** @var ActiveRow  */
    private $subscription;

    public function __construct(IRow $subscription)
    {
        $this->subscription = $subscription;
    }

    public function getSubscription(): IRow
    {
        return $this->subscription;
    }

    public function getUserId(): int
    {
        return $this->subscription->user_id;
    }
}
