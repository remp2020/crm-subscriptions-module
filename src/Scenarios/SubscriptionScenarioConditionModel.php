<?php

namespace Crm\SubscriptionsModule\Scenarios;

use Crm\ApplicationModule\Models\Criteria\ScenarioConditionModelInterface;
use Crm\ApplicationModule\Models\Database\Selection;
use Crm\SubscriptionsModule\Repositories\SubscriptionsRepository;
use Exception;

class SubscriptionScenarioConditionModel implements ScenarioConditionModelInterface
{
    public function __construct(
        private readonly SubscriptionsRepository $subscriptionsRepository,
    ) {
    }

    public function getItemQuery($scenarioJobParameters): Selection
    {
        if (!isset($scenarioJobParameters->subscription_id)) {
            throw new Exception("Subscription scenario conditional model requires 'subscription_id' job param.");
        }

        return $this->subscriptionsRepository->getTable()->where(['subscriptions.id' => $scenarioJobParameters->subscription_id]);
    }
}
