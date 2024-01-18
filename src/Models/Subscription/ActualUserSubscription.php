<?php

namespace Crm\SubscriptionsModule\Models\Subscription;

use Crm\SubscriptionsModule\Repositories\SubscriptionsRepository;
use Nette\Security\User;

class ActualUserSubscription
{
    private $user;

    private $subscriptionsRepository;

    private $isLoaded = false;

    private $actualSubscription;

    private $actualSubscriptions = null;

    private $nextSubscription;

    public function __construct(
        User $user,
        SubscriptionsRepository $subscriptionsRepository
    ) {
        $this->user = $user;
        $this->subscriptionsRepository = $subscriptionsRepository;
    }

    private function init()
    {
        if ($this->isLoaded) {
            return;
        }

        $this->isLoaded = true;

        if (!$this->user->isLoggedIn()) {
            return;
        }

        $this->actualSubscriptions = $this->subscriptionsRepository->actualUserSubscriptions($this->user->getId())->fetchAll();
        if (count($this->actualSubscriptions) == 0) {
            $this->nextSubscription = false;
            return;
        }

        $this->actualSubscription = current($this->actualSubscriptions);
        $this->nextSubscription = $this->subscriptionsRepository->find($this->actualSubscription->next_subscription_id);
    }

    public function hasActual()
    {
        $this->init();
        return $this->actualSubscription ? true : false;
    }

    public function getSubscription()
    {
        $this->init();
        return $this->actualSubscription;
    }

    public function getActualSubscriptionType()
    {
        $this->init();
        return $this->actualSubscription->subscription_type->code ?? null;
    }

    public function hasNextSubscription()
    {
        $this->init();
        return $this->nextSubscription ? true : false;
    }

    public function getNextSubscription()
    {
        $this->init();
        return $this->nextSubscription;
    }

    public function getActualRecurrentSubscription()
    {
        $this->init();
        foreach ($this->actualSubscriptions as $actualSubscription) {
            if ($actualSubscription->is_recurrent) {
                return $actualSubscription;
            }
        }
        return null;
    }
}
