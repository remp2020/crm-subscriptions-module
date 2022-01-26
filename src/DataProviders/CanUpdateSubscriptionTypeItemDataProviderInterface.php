<?php

namespace Crm\SubscriptionsModule\DataProvider;

use Crm\ApplicationModule\DataProvider\DataProviderInterface;
use Nette\Database\Table\IRow;

interface CanUpdateSubscriptionTypeItemDataProviderInterface extends DataProviderInterface
{
    /**
     * @param array{subscriptionTypeItem: IRow} $params
     */
    public function provide(array $params);
}
