<?php

namespace Crm\SubscriptionsModule\Scenarios;

use Contributte\Translation\Translator;
use Crm\ApplicationModule\Models\Criteria\ScenarioParams\BooleanParam;
use Crm\ApplicationModule\Models\Criteria\ScenariosCriteriaInterface;
use Nette\Database\Table\ActiveRow;
use Nette\Database\Table\Selection;

class SubscriptionTypeIsDefaultCriteria implements ScenariosCriteriaInterface
{
    const KEY = 'subscription_type_is_default';

    private $translator;

    public function __construct(
        Translator $translator,
    ) {
        $this->translator = $translator;
    }

    public function params(): array
    {
        return [
            new BooleanParam(self::KEY, $this->label()),
        ];
    }

    public function addConditions(Selection $selection, array $paramValues, ActiveRow $criterionItemRow): bool
    {
        $values = $paramValues[self::KEY];
        $selection->where('subscription_type.default = ?', $values->selection);

        return true;
    }

    public function label(): string
    {
        return $this->translator->translate('subscriptions.admin.scenarios.subscription_type_is_default.label');
    }
}
