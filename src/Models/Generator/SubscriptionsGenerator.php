<?php

namespace Crm\SubscriptionsModule\Models\Generator;

use Crm\SubscriptionsModule\Repositories\SubscriptionsRepository;
use League\Event\Emitter;

class SubscriptionsGenerator
{
    private $subscriptionsRepository;

    private $emitter;

    private $hermesEmitter;

    public function __construct(
        SubscriptionsRepository $subscriptionsRepository,
        Emitter $emitter,
        \Tomaj\Hermes\Emitter $hermesEmitter
    ) {
        $this->subscriptionsRepository = $subscriptionsRepository;
        $this->emitter = $emitter;
        $this->hermesEmitter = $hermesEmitter;
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
