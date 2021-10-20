<?php

namespace Crm\SubscriptionsModule\Extension;

use Nette\Database\Table\ActiveRow;

interface ExtensionInterface
{
    /**
     * @param ActiveRow $user
     * @param ActiveRow $subscriptionType
     * @return Extension
     */
    public function getStartTime(ActiveRow $user, ActiveRow $subscriptionType);
}
