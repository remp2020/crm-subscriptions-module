<?php

namespace Crm\SubscriptionsModule\Extension;

use Nette\Database\Table\IRow;

interface ExtensionInterface
{
    /**
     * @param IRow $user
     * @param IRow $subscriptionType
     * @return Extension
     */
    public function getStartTime(IRow $user, IRow $subscriptionType);
}
