<?php

namespace Crm\SubscriptionsModule\Models\Generator;

use Crm\SubscriptionsModule\Repositories\SubscriptionsRepository;

class SubscriptionsGenerator
{
    private $subscriptionsRepository;

    public function __construct(
        SubscriptionsRepository $subscriptionsRepository,
    ) {
        $this->subscriptionsRepository = $subscriptionsRepository;
    }

    public function generate(SubscriptionsParams $params, $count): array
    {
        $subscriptions = [];
        for ($i = 0; $i < $count; $i++) {
            $subscription = $this->subscriptionsRepository->add(
                $params->getSubscriptionType(),
                false,
                $params->getIsPaid(),
                $params->getUser(),
                $params->getType(),
                $params->getStartTime(),
                $params->getEndTime(),
                $params->getNote()
            );
            $subscriptions[] = $subscription;
        }
        return $subscriptions;
    }
}
