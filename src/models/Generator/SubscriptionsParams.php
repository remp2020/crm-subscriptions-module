<?php

namespace Crm\SubscriptionsModule\Generator;

use DateTime;
use Nette\Database\Table\IRow;

class SubscriptionsParams
{
    private $subscriptionType;

    private $user;

    private $startTime;

    private $endTime;

    private $type;

    /** @var bool */
    private $isPaid;

    public function __construct(
        IRow $subscriptionType,
        IRow $user,
        $type,
        DateTime $startTime,
        ?DateTime $endTime,
        bool $isPaid
    ) {
        $this->subscriptionType = $subscriptionType;
        $this->user = $user;
        $this->type = $type;
        $this->startTime = $startTime;
        $this->endTime = $endTime;
        $this->isPaid = $isPaid;
    }

    public function getSubscriptionType()
    {
        return $this->subscriptionType;
    }

    public function getStartTime()
    {
        return $this->startTime;
    }

    public function getEndTime()
    {
        return $this->endTime;
    }

    public function getUser()
    {
        return $this->user;
    }

    public function getType()
    {
        return $this->type;
    }

    public function getIsPaid(): bool
    {
        return $this->isPaid;
    }
}
