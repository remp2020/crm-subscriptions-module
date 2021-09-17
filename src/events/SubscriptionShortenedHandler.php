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
        $alignedSubscriptions = [
            $event->getBaseSubscription()->id => true,
        ];

        // $newStartTime is end time of the base subscription, unless there are some further following subscriptions.
        // In that case we'll follow them and $newStartTime is the end_time of the last in the chain.
        $followingSubscription = $this->getFollowingSubscription($event->getBaseSubscription(), $newStartTime);
        while ($followingSubscription) {
            $alignedSubscriptions[$followingSubscription->id] = true;
            $newStartTime = $followingSubscription->end_time;
            $followingSubscription = $this->getFollowingSubscription($followingSubscription, $newStartTime);
        }

        // Now we find the first subscription affected by baseSubscription shortening. There should be a gap to fill.
        $followingSubscription = $this->getFollowingSubscription($event->getBaseSubscription(), $event->getOriginalEndTime());
        while ($followingSubscription) {
            $followedEndTime = clone $followingSubscription->end_time;
            if (!isset($alignedSubscriptions[$followingSubscription->id])) {
                $newStartTime = $this->moveSubscription($followingSubscription, $newStartTime);
                $alignedSubscriptions[$followingSubscription->id] = true;
            }
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
        $subscription = $this->subscriptionsRepository->moveSubscription(
            $subscription,
            $startTime
        );

        return $subscription->end_time;
    }
}
