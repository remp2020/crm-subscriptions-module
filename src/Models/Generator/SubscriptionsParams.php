<?php

namespace Crm\SubscriptionsModule\Models\Generator;

use DateTime;
use Nette\Database\Table\ActiveRow;

class SubscriptionsParams
{
    private $subscriptionType;

    private $user;

    private $startTime;

    private $endTime;

    private $type;

    /** @var bool */
    private $isPaid;

    private $note;

    public function __construct(
        ActiveRow $subscriptionType,
        ActiveRow $user,
        $type,
        DateTime $startTime,
        ?DateTime $endTime,
        bool $isPaid,
        ?string $note = null,
    ) {
        $this->subscriptionType = $subscriptionType;
        $this->user = $user;
        $this->type = $type;
        $this->startTime = $startTime;
        $this->endTime = $endTime;
        $this->isPaid = $isPaid;
        $this->note = $note;
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

    public function getNote(): ?string
    {
        return $this->note;
    }
}
