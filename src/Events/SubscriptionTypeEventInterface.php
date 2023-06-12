<?php

namespace Crm\SubscriptionsModule\Events;

use Nette\Database\Table\ActiveRow;

interface SubscriptionTypeEventInterface
{
    public function getSubscriptionType(): ?ActiveRow;
}
