<?php

namespace Crm\SubscriptionsModule\Events;

use Nette\Database\Table\ActiveRow;

interface SubscriptionEventInterface
{
    public function getSubscription(): ?ActiveRow;
}