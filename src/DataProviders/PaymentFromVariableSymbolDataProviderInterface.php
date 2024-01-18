<?php

namespace Crm\SubscriptionsModule\DataProviders;

use Crm\ApplicationModule\DataProvider\DataProviderInterface;
use Nette\Database\Table\ActiveRow;

interface PaymentFromVariableSymbolDataProviderInterface extends DataProviderInterface
{
    /**
     * Provide payment ActiveRow based on variable symbol
     *
     * @param array $params {
     *   @type string $variableSymbol
     * }
     *
     * @return null|ActiveRow
     */
    public function provide(array $params): ?ActiveRow;
}
