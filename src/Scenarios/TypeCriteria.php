<?php

namespace Crm\SubscriptionsModule\Scenarios;

use Crm\ApplicationModule\Criteria\ScenarioParams\StringLabeledArrayParam;
use Crm\ApplicationModule\Criteria\ScenariosCriteriaInterface;
use Crm\SubscriptionsModule\Repository\SubscriptionsRepository;
use Nette\Database\Table\ActiveRow;
use Nette\Database\Table\Selection;

class TypeCriteria implements ScenariosCriteriaInterface
{
    private $subscriptionsRepository;

    public function __construct(
        SubscriptionsRepository $subscriptionsRepository
    ) {
        $this->subscriptionsRepository = $subscriptionsRepository;
    }

    public function params(): array
    {
        $types = $this->subscriptionsRepository->availableTypes();

        return [
            new StringLabeledArrayParam('type', 'Type', $types),
        ];
    }

    public function addConditions(Selection $selection, array $paramValues, ActiveRow $criterionItemRow): bool
    {
        $values = $paramValues['type'];
        $selection->where('subscriptions.type IN (?)', $values->selection);

        return true;
    }

    public function label(): string
    {
        return 'Type';
    }
}
