<?php

namespace Crm\SubscriptionsModule\Models\Extension;

use Crm\ApplicationModule\Models\NowTrait;
use Crm\SubscriptionsModule\Repositories\SubscriptionsRepository;
use Nette\Database\Table\ActiveRow;

/**
 * Extends:
 * - actual (current) subscription of same subscription type;
 * - or starts immediately.
 */
class ExtendSameActualExtension implements ExtensionInterface
{
    use NowTrait;

    public const METHOD_CODE = 'extend_same_actual';
    public const METHOD_NAME = 'Extend same actual';

    public function __construct(
        private SubscriptionsRepository $subscriptionsRepository,
    ) {
    }

    public function getStartTime(ActiveRow $user, ActiveRow $subscriptionType, ?ActiveRow $address = null): Extension
    {
        $latestActualSubscriptionForType = $this->subscriptionsRepository
            ->actualUserSubscriptions($user->id)
            ->where('subscription_type_id', $subscriptionType->id)
            ->fetch();

        if ($latestActualSubscriptionForType) {
            return new Extension($latestActualSubscriptionForType->end_time, true);
        }

        return new Extension($this->getNow());
    }
}
