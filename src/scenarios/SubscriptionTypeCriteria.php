<?php

namespace Crm\SubscriptionsModule\Scenarios;

use Crm\ApplicationModule\Criteria\Params\StringLabeledArrayParam;
use Crm\ApplicationModule\Criteria\ScenariosCriteriaInterface;
use Crm\SubscriptionsModule\Repository\SubscriptionTypesRepository;
use Nette\Database\Table\IRow;
use Nette\Database\Table\Selection;

class SubscriptionTypeCriteria implements ScenariosCriteriaInterface
{
    private $subscriptionTypesRepository;

    public function __construct(
        SubscriptionTypesRepository $subscriptionTypesRepository
    ) {
        $this->subscriptionTypesRepository = $subscriptionTypesRepository;
    }

    public function params(): array
    {
        $types = $this->subscriptionTypesRepository->all()->fetchPairs('code', 'name');

        return [
            new StringLabeledArrayParam('subscription_type', 'Subscription type', $types),
        ];
    }

    public function addCondition(Selection $selection, $values, IRow $criterionItemRow): bool
    {
        $selection->where('subscription_type.code IN (?)', $values->selection);

        return true;
    }

    public function label(): string
    {
        return 'Subscription type';
    }
}
