<?php

namespace Crm\SubscriptionsModule\Scenarios;

use Crm\ApplicationModule\Models\Criteria\ScenarioParams\NumberParam;
use Crm\ApplicationModule\Models\Criteria\ScenariosCriteriaInterface;
use Nette\Database\Table\ActiveRow;
use Nette\Database\Table\Selection;
use Nette\Utils\Json;

class SubscriptionTypeLengthCriteria implements ScenariosCriteriaInterface
{
    const KEY = 'subscription_type_length';

    private const OPERATORS = ['=', '>', '<', '<=', '>='];

    public function params(): array
    {
        return [
            new NumberParam(self::KEY, 'Subscription type length', 'Days', self::OPERATORS, ['min' => 0]),
        ];
    }

    public function addConditions(Selection $selection, array $paramValues, ActiveRow $criterionItemRow): bool
    {
        $values = $paramValues[self::KEY];

        $operator = $values->operator;
        if (!in_array($operator, self::OPERATORS, true)) {
            throw new \Exception("Operator $operator is not a valid operator out of: " . Json::encode(self::OPERATORS));
        }

        $selection->where('subscription_type.length ' . $operator . ' ?', $values->selection);
        return true;
    }

    public function label(): string
    {
        return 'Subscription type length';
    }
}
