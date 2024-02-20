<?php

namespace Crm\SubscriptionsModule\Models\Extension;

use Nette\Database\Table\ActiveRow;

interface ExtensionInterface
{
    public function getStartTime(ActiveRow $user, ActiveRow $subscriptionType, ?ActiveRow $address = null): Extension;
}
