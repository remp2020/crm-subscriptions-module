<?php

namespace Crm\SubscriptionsModule\Scenarios;

use Crm\ApplicationModule\Models\Criteria\ScenarioConditionModelInterface;
use Crm\ApplicationModule\Models\Criteria\ScenarioConditionModelRequirementsInterface;
use Crm\ApplicationModule\Models\Database\Selection;
use Crm\ScenariosModule\Events\ConditionCheckException;
use Crm\SubscriptionsModule\Repositories\SubscriptionsRepository;

class SubscriptionScenarioConditionModel implements ScenarioConditionModelInterface, ScenarioConditionModelRequirementsInterface
{
    public function __construct(
        private readonly SubscriptionsRepository $subscriptionsRepository,
    ) {
    }

    public function getInputParams(): array
    {
        return ['subscription_id'];
    }

    public function getItemQuery($scenarioJobParameters): Selection
    {
        if (!isset($scenarioJobParameters->subscription_id)) {
            throw new ConditionCheckException("Subscription scenario conditional model requires 'subscription_id' job param.");
        }

        return $this->subscriptionsRepository->getTable()->where(['subscriptions.id' => $scenarioJobParameters->subscription_id]);
    }
}
