<?php

namespace Crm\SubscriptionsModule\Models\Subscription;

use Crm\SubscriptionsModule\Repositories\SubscriptionMetaRepository;
use Nette\Database\Table\ActiveRow;

class SubscriptionEndsSuppressionManager
{
    public const SUPPRESS_SUBSCRIPTION_ENDS_NOTIFICATION = 'suppress_subscription_ends_notification';

    public function __construct(
        private SubscriptionMetaRepository $subscriptionMetaRepository,
    ) {
    }

    public function hasSuppressedNotifications(ActiveRow $subscription): bool
    {
        return (bool) $subscription->related('subscriptions_meta')->where([
            'key' => self::SUPPRESS_SUBSCRIPTION_ENDS_NOTIFICATION,
            'value' => true
        ])->count();
    }

    public function suppressNotifications(ActiveRow $subscription): void
    {
        $this->subscriptionMetaRepository->setMeta($subscription, self::SUPPRESS_SUBSCRIPTION_ENDS_NOTIFICATION, true);
    }

    public function resumeNotifications(ActiveRow $subscription): void
    {
        $this->subscriptionMetaRepository->setMeta($subscription, self::SUPPRESS_SUBSCRIPTION_ENDS_NOTIFICATION, false);
    }
}
