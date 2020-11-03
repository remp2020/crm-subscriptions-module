<?php

namespace Crm\SubscriptionsModule\DataProvider;

use Crm\ApplicationModule\DataProvider\DataProviderInterface;
use Nette\Database\Table\IRow;

interface CanUpdateSubscriptionTypeItemDataProviderInterface extends DataProviderInterface
{
    /**
     * @param array $params {
     *   @type IRow $subscriptionTypeItem
     * }
     */
    public function provide(array $params);
}
