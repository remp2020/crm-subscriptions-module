<?php

namespace Crm\SubscriptionsModule\DataProviders;

use Crm\ApplicationModule\DataProvider\DataProviderInterface;
use Nette\Database\Table\ActiveRow;

interface CanUpdateSubscriptionTypeItemDataProviderInterface extends DataProviderInterface
{
    /**
     * @param array{subscriptionTypeItem: ActiveRow} $params
     */
    public function provide(array $params);
}
