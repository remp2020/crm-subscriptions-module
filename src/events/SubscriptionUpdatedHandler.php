<?php

namespace Crm\SubscriptionsModule\Events;

use Crm\SubscriptionsModule\Repository\SubscriptionsRepository;
use Crm\UsersModule\User\ISubscriptionGetter;
use Crm\UsersModule\User\IUserGetter;
use Crm\UsersModule\User\UserData;
use League\Event\AbstractListener;
use League\Event\Emitter;
use League\Event\EventInterface;

class SubscriptionUpdatedHandler extends AbstractListener
{
    private $userData;

    private $subscriptionsRepository;

    private $emitter;

    public function __construct(
        UserData $userData,
        Emitter $emitter,
        SubscriptionsRepository $subscriptionsRepository
    ) {
        $this->userData = $userData;
        $this->subscriptionsRepository = $subscriptionsRepository;
        $this->emitter = $emitter;
    }

    public function handle(EventInterface $event)
    {
        if (!($event instanceof IUserGetter) || !($event instanceof ISubscriptionGetter)) {
            throw new \Exception('cannot handle event, invalid instance received: ' . get_class($event));
        }

        $subscription = $event->getSubscription();

        $started = $this->subscriptionsRepository->getStartedSubscriptions()->where(['id' => $subscription->id])->count('*');
        if ($started) {
            $this->subscriptionsRepository->setStarted($subscription);
        }

        $expired = $this->subscriptionsRepository->getExpiredSubscriptions()->where(['id' => $subscription->id])->count('*');
        if ($expired) {
            $this->subscriptionsRepository->setExpired($subscription);
        }

        $userId = $event->getUserId();
        $this->userData->refreshUserTokens($userId);
    }
}
