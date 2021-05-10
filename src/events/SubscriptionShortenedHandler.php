<?php

namespace Crm\SubscriptionsModule\Events;

use Crm\SubscriptionsModule\Repository\SubscriptionsRepository;
use DateTime;
use League\Event\AbstractListener;
use League\Event\EventInterface;
use Nette\Database\Table\ActiveRow;

/**
 * SubscriptionShortenedHandler handles "holes" that could appear when you shorten a subscription and user already
 * has other subsequent subscriptions.
 *
 * Handler is responsible for finding all subsequent (directly continuing) subscriptions and making sure, that they'll
 * directly continue also when the current subscription (initial subscription in chain) is shortened.
 */
class SubscriptionShortenedHandler extends AbstractListener
{
    private $subscriptionsRepository;

    public function __construct(SubscriptionsRepository $subscriptionsRepository)
    {
        $this->subscriptionsRepository = $subscriptionsRepository;
    }

    public function handle(EventInterface $event)
    {
        if (!$event instanceof SubscriptionShortenedEvent) {
            throw new \Exception('Invalid type of event received, SubscriptionShortenedEvent expected: ' . get_class($event));
        }

        $newStartTime = $event->getBaseSubscription()->end_time;
        // following new end time of base subscription
        $followingSubscription = $this->getFollowingSubscription($event->getBaseSubscription(), $newStartTime);
        while ($followingSubscription) {
            $newStartTime = $followingSubscription->end_time;
            $followingSubscription = $this->getFollowingSubscription($followingSubscription, $newStartTime);
        }

        // following original end time of base subscription
        $followingSubscription = $this->getFollowingSubscription($event->getBaseSubscription(), $event->getOriginalEndTime());
        while ($followingSubscription) {
            $followedEndTime = clone $followingSubscription->end_time;
            $newStartTime = $this->moveSubscription($followingSubscription, $newStartTime);
            $followingSubscription = $this->getFollowingSubscription($followingSubscription, $followedEndTime);
        }
    }

    private function getFollowingSubscription(ActiveRow $subscription, DateTime $previousSubscriptionEndTime)
    {
        return $this->subscriptionsRepository->getTable()
            ->where([
                'id != ?' => $subscription->id,
                'user_id' => $subscription->user_id,
                'start_time' => $previousSubscriptionEndTime, // search for subscriptions, which start at the same time as previous ends
            ])
            ->where('start_time < end_time')
            ->fetch();
    }

    private function moveSubscription(ActiveRow &$subscription, DateTime $startTime)
    {
        $lengthInSeconds = $subscription->end_time->getTimestamp() - $subscription->start_time->getTimestamp();
        $endTime = (clone $startTime)->add(new \DateInterval("PT{$lengthInSeconds}S"));

        $this->subscriptionsRepository->update($subscription, [
            'start_time' => $startTime,
            'end_time' => $endTime,
        ]);

        return $endTime;
    }
}
