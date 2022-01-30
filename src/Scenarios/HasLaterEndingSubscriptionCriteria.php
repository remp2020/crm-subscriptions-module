<?php

namespace Crm\SubscriptionsModule\Scenarios;

use Crm\ApplicationModule\Criteria\ScenarioParams\BooleanParam;
use Crm\ApplicationModule\Criteria\ScenariosCriteriaInterface;
use Kdyby\Translation\Translator;
use Nette\Database\Table\ActiveRow;
use Nette\Database\Table\Selection;

class HasLaterEndingSubscriptionCriteria implements ScenariosCriteriaInterface
{
    public const KEY = 'has_later_ending_subscription';

    private $translator;

    public function __construct(Translator $translator)
    {
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

        if ($values->selection) {
            $selection->alias(".user:subscriptions(user)", "s")
                ->where("s.end_time > subscriptions.end_time AND s.id");
        } else {
            $selection->alias(".user:subscriptions(user)", "s")
                ->joinWhere("s", "s.end_time > subscriptions.end_time")
                ->where("s.id IS NULL");
        }

        return true;
    }

    public function label(): string
    {
        return $this->translator->translate('subscriptions.admin.scenarios.has_later_ending_subscription.label');
    }
}
