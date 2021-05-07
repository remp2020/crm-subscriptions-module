<?php

namespace Crm\SubscriptionsModule\Subscription;

use Crm\SubscriptionsModule\Events\SubscriptionShortenedEvent;
use Crm\SubscriptionsModule\Repository\SubscriptionMetaRepository;
use Crm\SubscriptionsModule\Repository\SubscriptionsRepository;
use DateTime;
use League\Event\Emitter;
use Nette\Database\Table\IRow;

class StopSubscriptionHandler
{
    private $subscriptionsRepository;

    private $subscriptionMetaRepository;

    private $emitter;

    public function __construct(
        SubscriptionsRepository $subscriptionsRepository,
        SubscriptionMetaRepository $subscriptionMetaRepository,
        Emitter $emitter
    ) {
        $this->subscriptionsRepository = $subscriptionsRepository;
        $this->subscriptionMetaRepository = $subscriptionMetaRepository;
        $this->emitter = $emitter;
    }

    public function stopSubscription(IRow $subscription): void
    {
        $originalEndTime = clone $subscription->end_time;
        $newEndTime = new DateTime();
        // subscription has not started yet
        if ($newEndTime < $subscription->start_time) {
            $newEndTime = $subscription->start_time;
        }

        $note = '[Admin stop] Original end_time ' . $originalEndTime;
        if (!empty($subscription->note)) {
            $note = $subscription->note . "\n" . $note;
        }

        $this->subscriptionMetaRepository->setMeta($subscription, 'expired_by_admin', true);
        $this->subscriptionsRepository->update($subscription, [
            'end_time' => $newEndTime,
            'note' => $note
        ]);

        $this->emitter->emit(new SubscriptionShortenedEvent($subscription, $originalEndTime));
    }
}
