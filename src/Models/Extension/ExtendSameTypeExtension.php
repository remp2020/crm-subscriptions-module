<?php

namespace Crm\SubscriptionsModule\Models\Extension;

use Crm\ApplicationModule\Models\NowTrait;
use Crm\SubscriptionsModule\Repositories\SubscriptionsRepository;
use DateTime;
use Nette\Database\Table\ActiveRow;

/**
 * Extends:
 * - last subscription of same subscription type;
 * - or actual (current) subscription (of any type);
 * - or starts immediately.
 */
class ExtendSameTypeExtension implements ExtensionInterface
{
    use NowTrait;

    public const METHOD_CODE = 'extend_same_type';
    public const METHOD_NAME = 'Extend same type';

    public function __construct(
        private SubscriptionsRepository $subscriptionsRepository,
    ) {
    }

    public function getStartTime(ActiveRow $user, ActiveRow $subscriptionType, ?ActiveRow $address = null): Extension
    {
        $lifetimeThreshold = new DateTime('+ 30 years');
        $lastSubscriptionTypeSubscription = $this->subscriptionsRepository->userSubscriptions($user->id)
            ->where('subscription_type_id', $subscriptionType->id)
            ->where('end_time > ?', $this->getNow())
            ->where('end_time <', $lifetimeThreshold)
            ->fetch();
        if ($lastSubscriptionTypeSubscription) {
            return new Extension($lastSubscriptionTypeSubscription->end_time, true);
        }

        $actualSubscription = $this->subscriptionsRepository->actualUserSubscription($user->id);
        if ($actualSubscription) {
            if ($actualSubscription->end_time < $lifetimeThreshold) {
                return new Extension($actualSubscription->end_time, $subscriptionType->id === $actualSubscription->subscription_type_id);
            }
        }
        return new Extension($this->getNow());
    }
}
