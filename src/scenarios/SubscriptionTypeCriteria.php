<?php

namespace Crm\SubscriptionsModule\Scenarios;

use Crm\ApplicationModule\Criteria\ScenarioParams\StringLabeledArrayParam;
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
        $options = [];
        foreach ($this->subscriptionTypesRepository->all()->select('code, name') as $subscriptionType) {
            $options[$subscriptionType->code] = [
                'label' => $subscriptionType->name,
                'subtitle' => "($subscriptionType->code)",
            ];
        }

        return [
            new StringLabeledArrayParam('subscription_type', 'Subscription type', $options),
        ];
    }

    public function addConditions(Selection $selection, array $paramValues, IRow $criterionItemRow): bool
    {
        $values = $paramValues['subscription_type'];
        $selection->where('subscription_type.code IN (?)', $values->selection);

        return true;
    }

    public function label(): string
    {
        return 'Subscription type';
    }
}
