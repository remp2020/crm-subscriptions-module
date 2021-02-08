<?php

namespace Crm\SubscriptionsModule\Scenarios;

use Crm\ApplicationModule\Criteria\ScenarioParams\NumberParam;
use Crm\ApplicationModule\Criteria\ScenariosCriteriaInterface;
use Nette\Database\Table\IRow;
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

    public function addCondition(Selection $selection, $values, IRow $criterionItemRow): bool
    {
        $operator = $values->operator;
        if (!in_array($operator, self::OPERATORS, true)) {
            throw new \Exception("Operator $operator is not a valid operator out of: " . Json::encode(self::OPERATORS));
        }

        $selection->where('.subscription_type.length ' . $operator . ' ?', $values->selection);
        return true;
    }

    public function label(): string
    {
        return 'Subscription type length';
    }
}
