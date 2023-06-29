<?php
declare(strict_types=1);

namespace Crm\SubscriptionsModule\Scenarios;

use Contributte\Translation\Translator;
use Crm\ApplicationModule\Criteria\ScenarioParams\NumberParam;
use Crm\ApplicationModule\Criteria\ScenarioParams\StringLabeledArrayParam;
use Crm\ApplicationModule\Criteria\ScenariosCriteriaInterface;
use Crm\SubscriptionsModule\Repository\ContentAccessRepository;
use Nette\Database\Table\ActiveRow;
use Nette\Database\Table\Selection;

class FirstSubscriptionInPeriodCriteria implements ScenariosCriteriaInterface
{
    public const KEY = 'first_subscription_in_period';

    public const PERIOD_KEY = 'first_subscription_in_period_period';
    public const CONTENT_ACCESS_KEY = 'first_subscription_in_period_content_access';

    public function __construct(
        private ContentAccessRepository $contentAccessRepository,
        private Translator $translator,
    ) {
    }

    public function params(): array
    {
        $contentAccesses = $this->contentAccessRepository->all()->fetchPairs('name', 'description');

        return [
            new NumberParam(
                self::PERIOD_KEY,
                $this->translator->translate('subscriptions.admin.scenarios.first_subscription_in_period.period.label'),
                $this->translator->translate('subscriptions.admin.scenarios.first_subscription_in_period.period.unit'),
                ['<='],
                ['min' => 1, 'step' => 1],
            ),
            new StringLabeledArrayParam(
                self::CONTENT_ACCESS_KEY,
                $this->translator->translate('subscriptions.admin.scenarios.first_subscription_in_period.content_access.label'),
                $contentAccesses,
            ),
        ];
    }

    public function addConditions(Selection $selection, array $paramValues, ActiveRow $criterionItemRow): bool
    {
        $intervalDays = filter_var($paramValues[self::PERIOD_KEY]->selection, FILTER_VALIDATE_INT, ["options" => ["min_range"=> 1]]);
        if ($intervalDays === false) {
            throw new \Exception("Provided value [{$paramValues[self::PERIOD_KEY]->selection}] for number of days is not valid positive integer.");
        }

        $selection
            ->alias(".user:subscriptions(user)", "previous_subscriptions")
            ->joinWhere(
                "previous_subscriptions",
                "previous_subscriptions.user_id = subscriptions.user_id
                AND previous_subscriptions.start_time < subscriptions.start_time
                AND previous_subscriptions.start_time > NOW() - INTERVAL ? DAY",
                $intervalDays
            );

        // if content access filter was not used,
        // check only if previous subscription (of any content access) exists within provided period
        if (!isset($paramValues[self::CONTENT_ACCESS_KEY]->selection)) {
            $selection->where('previous_subscriptions.id IS NULL');
        } else {
            // otherwise check if previous subscription exists within period
            // and if it does, content access cannot be one of provided accesses
            // and current subscription has to contain provided content access
            $selection
                ->where(
                    'subscription_type:subscription_type_content_access.content_access.name IN (?)',
                    $paramValues[self::CONTENT_ACCESS_KEY]->selection,
                );
            $selection
                ->alias('previous_subscriptions.subscription_type', 'previous_subscription_type')
                ->alias('previous_subscription_type:subscription_type_content_access', 'previous_subscription_type_content_access')
                ->alias('previous_subscription_type_content_access.content_access', 'previous_subscription_type_content_access_content_access')
                ->whereOr([
                    'previous_subscriptions.id IS NULL',
                    'previous_subscription_type_content_access_content_access.name NOT IN (?)' => $paramValues[self::CONTENT_ACCESS_KEY]->selection
            ]);
        }

        return true;
    }

    public function label(): string
    {
        return $this->translator->translate('subscriptions.admin.scenarios.first_subscription_in_period.period.label');
    }
}
