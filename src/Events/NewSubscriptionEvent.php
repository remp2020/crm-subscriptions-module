<?php

namespace Crm\SubscriptionsModule\Events;

use Crm\UsersModule\Models\User\IUserGetter;
use League\Event\AbstractEvent;
use Nette\Database\Table\ActiveRow;

class NewSubscriptionEvent extends AbstractEvent implements IUserGetter, SubscriptionEventInterface
{
    /** @var ActiveRow  */
    private $subscription;

    private $sendEmail;

    public function __construct(ActiveRow $subscription, $sendEmail = true)
    {
        $this->subscription = $subscription;
        $this->sendEmail = $sendEmail;
    }

    public function getSubscription(): ActiveRow
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
