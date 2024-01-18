<?php

namespace Crm\SubscriptionsModule\Models\Subscription;

use Crm\SubscriptionsModule\Events\SubscriptionShortenedEvent;
use Crm\SubscriptionsModule\Repositories\SubscriptionMetaRepository;
use Crm\SubscriptionsModule\Repositories\SubscriptionsRepository;
use DateTime;
use League\Event\Emitter;
use Nette\Database\Table\ActiveRow;

class StopSubscriptionHandler
{
    public const META_KEY_EXPIRED_BY_ADMIN = 'expired_by_admin';

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

    /**
     * @param ActiveRow $subscription Subscription to expire.
     * @param bool|null $expiredByAdmin Flag whether the subscription was expired by admin or not. If the information
     * cannot be reliably provided, feel free to pass null.
     * @return void
     * @throws \Exception
     */
    public function stopSubscription(ActiveRow $subscription, ?bool $expiredByAdmin = null): void
    {
        $originalEndTime = clone $subscription->end_time;
        $newEndTime = new DateTime();
        // subscription has not started yet
        if ($newEndTime < $subscription->start_time) {
            $newEndTime = $subscription->start_time;
        }

        $note = $subscription->note;
        if ($expiredByAdmin) {
            $note = '[Admin stop] Original end_time ' . $originalEndTime;
            if (!empty($subscription->note)) {
                $note = $subscription->note . "\n" . $note;
            }
            $this->subscriptionMetaRepository->setMeta($subscription, self::META_KEY_EXPIRED_BY_ADMIN, $expiredByAdmin);
        }

        $this->subscriptionsRepository->update($subscription, [
            'end_time' => $newEndTime,
            'note' => $note,
        ]);

        $this->emitter->emit(new SubscriptionShortenedEvent($subscription, $originalEndTime));
    }
}
