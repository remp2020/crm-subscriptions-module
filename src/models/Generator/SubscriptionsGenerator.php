<?php

namespace Crm\SubscriptionsModule\Generator;

use Crm\SubscriptionsModule\Events\SubscriptionStartsEvent;
use Crm\SubscriptionsModule\Repository\SubscriptionsRepository;
use DateTime;
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

            if ($subscription->start_time <= new DateTime() and $subscription->end_time > new DateTime()) {
                $this->subscriptionsRepository->update($subscription, ['internal_status' => SubscriptionsRepository::INTERNAL_STATUS_ACTIVE]);
                $this->emitter->emit(new SubscriptionStartsEvent($subscription));
            } else {
                $this->subscriptionsRepository->update($subscription, ['internal_status' => SubscriptionsRepository::INTERNAL_STATUS_BEFORE_START]);
            }
            $subscriptions[] = $subscription;
        }
        return $subscriptions;
    }
}
