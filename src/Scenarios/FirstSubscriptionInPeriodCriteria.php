<?php
declare(strict_types=1);

namespace Crm\SubscriptionsModule\Scenarios;

use Contributte\Translation\Translator;
use Crm\ApplicationModule\Criteria\ScenarioParams\NumberParam;
use Crm\ApplicationModule\Criteria\ScenariosCriteriaInterface;
use Nette\Database\Table\ActiveRow;
use Nette\Database\Table\Selection;

class FirstSubscriptionInPeriodCriteria implements ScenariosCriteriaInterface
{
    public const KEY = 'first_subscription_in_period';

    public function __construct(
        private Translator $translator,
    ) {
    }

    public function params(): array
    {
        return [
            new NumberParam(
                self::KEY,
                $this->translator->translate('subscriptions.admin.scenarios.first_subscription_in_period.label'),
                $this->translator->translate('subscriptions.admin.scenarios.first_subscription_in_period.unit'),
                ['<='],
                ['min' => 1, 'step' => 1],
            ),
        ];
    }

    public function addConditions(Selection $selection, array $paramValues, ActiveRow $criterionItemRow): bool
    {
        $intervalDays = filter_var($paramValues[self::KEY]->selection, FILTER_VALIDATE_INT, ["options" => ["min_range"=> 1]]);
        if ($intervalDays === false) {
            throw new \Exception("Provided value [{$paramValues[self::KEY]->selection}] for number of days is not valid positive integer.");
        }

        $selection
            ->alias(".user:subscriptions(user)", "previous_subscriptions")
            ->joinWhere(
                "previous_subscriptions",
                "previous_subscriptions.user_id = subscriptions.user_id
                AND previous_subscriptions.start_time < subscriptions.start_time
                AND previous_subscriptions.start_time > NOW() - INTERVAL ? DAY",
                $intervalDays
            )
            ->where('previous_subscriptions.id IS NULL');

        return true;
    }

    public function label(): string
    {
        return $this->translator->translate('subscriptions.admin.scenarios.first_subscription_in_period.label');
    }
}
