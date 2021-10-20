<?php

namespace Crm\SubscriptionsModule\Scenarios;

use Crm\ApplicationModule\Criteria\ScenarioParams\BooleanParam;
use Crm\ApplicationModule\Criteria\ScenariosCriteriaInterface;
use Nette\Database\Table\ActiveRow;
use Nette\Database\Table\Selection;

class HasDisabledNotificationsCriteria implements ScenariosCriteriaInterface
{
    public const KEY = 'has_disabled_notifications';

    public function params(): array
    {
        return [
            new BooleanParam(self::KEY, $this->label()),
        ];
    }

    public function addConditions(Selection $selection, array $paramValues, ActiveRow $criterionItemRow): bool
    {
        $values = $paramValues[self::KEY];
        $selection->where('subscription_type.disable_notifications = ?', (int) $values->selection);

        return true;
    }

    public function label(): string
    {
        return 'Has disabled notifications';
    }
}
