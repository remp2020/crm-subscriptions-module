<?php

namespace Crm\SubscriptionsModule\Events;

use Crm\UsersModule\User\ISubscriptionGetter;
use Crm\UsersModule\User\IUserGetter;
use League\Event\AbstractEvent;
use Nette\Database\Table\IRow;

class NewSubscriptionEvent extends AbstractEvent implements IUserGetter, ISubscriptionGetter
{
    /** @var IRow  */
    private $subscription;

    private $sendEmail;

    public function __construct(IRow $subscription, $sendEmail = true)
    {
        $this->subscription = $subscription;
        $this->sendEmail = $sendEmail;
    }

    public function getSubscription(): IRow
    {
        return $this->subscription;
    }

    public function getSendEmail()
    {
        return $this->sendEmail;
    }

    public function getUserId(): int
    {
        return $this->subscription->user_id;
    }
}
