<?php

namespace Crm\SubscriptionsModule\Scenarios;

use Crm\ApplicationModule\Criteria\ScenarioParams\BooleanParam;
use Crm\ApplicationModule\Criteria\ScenariosCriteriaInterface;
use Nette\Database\Table\IRow;
use Nette\Database\Table\Selection;

class IsRecurrentCriteria implements ScenariosCriteriaInterface
{
    public function params(): array
    {
        return [
            new BooleanParam('is_recurrent', $this->label()),
        ];
    }

    public function addCondition(Selection $selection, $values, IRow $criterionItemRow): bool
    {
        $selection->where('subscriptions.is_recurrent = ?', $values->selection);

        return true;
    }

    public function label(): string
    {
        return 'Is recurrent';
    }
}
