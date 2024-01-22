<?php

namespace Crm\SubscriptionsModule\Scenarios;

use Crm\ApplicationModule\Models\Criteria\ScenarioParams\BooleanParam;
use Crm\ApplicationModule\Models\Criteria\ScenariosCriteriaInterface;
use Nette\Database\Table\ActiveRow;
use Nette\Database\Table\Selection;

class IsRecurrentCriteria implements ScenariosCriteriaInterface
{
    public function params(): array
    {
        return [
            new BooleanParam('is_recurrent', $this->label()),
        ];
    }

    public function addConditions(Selection $selection, array $paramValues, ActiveRow $criterionItemRow): bool
    {
        $values = $paramValues['is_recurrent'];
        $selection->where('subscriptions.is_recurrent = ?', $values->selection);

        return true;
    }

    public function label(): string
    {
        return 'Is recurrent';
    }
}
