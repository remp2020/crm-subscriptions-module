<?php

namespace Crm\SubscriptionsModule\Events;

use Crm\UsersModule\User\ISubscriptionGetter;
use Crm\UsersModule\User\IUserGetter;
use League\Event\AbstractEvent;
use Nette\Database\Table\ActiveRow;
use Nette\Database\Table\IRow;

/**
 * Event emitted in case of subscription's basic parameters (start_time, end_time, subscription_type_id, user_id) update
 * Class SubscriptionUpdatedEvent
 * @package Crm\SubscriptionsModule\Events
 */
class SubscriptionUpdatedEvent extends AbstractEvent implements IUserGetter, ISubscriptionGetter
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
