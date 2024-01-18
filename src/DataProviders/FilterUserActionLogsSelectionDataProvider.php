<?php

namespace Crm\SubscriptionsModule\DataProviders;

use Crm\ApplicationModule\DataProvider\DataProviderException;
use Crm\ApplicationModule\Selection;
use Crm\SubscriptionsModule\Repositories\SubscriptionTypesRepository;
use Crm\UsersModule\DataProvider\FilterUserActionLogsDataProviderInterface;

class FilterUserActionLogsSelectionDataProvider implements FilterUserActionLogsDataProviderInterface
{
    private $subscriptionTypesRepository;

    public function __construct(
        SubscriptionTypesRepository $subscriptionTypesRepository
    ) {
        $this->subscriptionTypesRepository = $subscriptionTypesRepository;
    }

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
