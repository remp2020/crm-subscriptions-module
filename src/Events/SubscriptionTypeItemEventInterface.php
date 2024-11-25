<?php

namespace Crm\SubscriptionsModule\Events;

use Nette\Database\Table\ActiveRow;

interface SubscriptionTypeItemEventInterface
{
    public function getSubscriptionTypeItem(): ?ActiveRow;
}
