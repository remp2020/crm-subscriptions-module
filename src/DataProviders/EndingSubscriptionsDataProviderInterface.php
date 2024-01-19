<?php

namespace Crm\SubscriptionsModule\DataProviders;

use Crm\ApplicationModule\Models\DataProvider\DataProviderInterface;
use Crm\ApplicationModule\Models\Graphs\GraphDataItem;

interface EndingSubscriptionsDataProviderInterface extends DataProviderInterface
{
    /**
     * @param array $params {
     *   @type string $dateFrom
     *   @type string $dateTo
     * }
     * @return GraphDataItem
     */
    public function provide(array $params): GraphDataItem;
}
