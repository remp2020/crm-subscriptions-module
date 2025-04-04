<?php

namespace Crm\SubscriptionsModule\DataProviders;

use Crm\ApplicationModule\Models\DataProvider\DataProviderException;
use Crm\ApplicationModule\Models\Database\Selection;
use Crm\UsersModule\DataProviders\FilterUserActionLogsDataProviderInterface;

class FilterUserActionLogsSelectionDataProvider implements FilterUserActionLogsDataProviderInterface
{
    public function provide(array $params): Selection
    {
        if (!isset($params['selection'])) {
            throw new DataProviderException('selection param missing');
        }
        if (!isset($params['params'])) {
            throw new DataProviderException('params param missing');
        }

        if (isset($params['params']['subscriptionTypeId'])) {
            $params['selection']
                ->where(['JSON_EXTRACT(params, "$.subscription_type_id")' => intval($params['params']['subscriptionTypeId'])]);
        }

        return $params['selection'];
    }
}
