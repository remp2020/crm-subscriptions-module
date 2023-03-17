<?php

namespace Crm\SubscriptionsModule\Extension;

use Nette\Database\Table\ActiveRow;

interface ExtensionInterface
{
    public function getStartTime(ActiveRow $user, ActiveRow $subscriptionType): Extension;
}
