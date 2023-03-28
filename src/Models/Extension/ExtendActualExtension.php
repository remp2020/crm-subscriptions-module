<?php

namespace Crm\SubscriptionsModule\Extension;

use Crm\ApplicationModule\NowTrait;
use Crm\SubscriptionsModule\Repository\SubscriptionsRepository;
use Nette\Database\Table\ActiveRow;

/**
 * Extends:
 * - actual (current) subscription (of any type);
 * - or starts immediately.
 */
class ExtendActualExtension implements ExtensionInterface
{
    use NowTrait;

    public const METHOD_CODE = 'extend_actual';
    public const METHOD_NAME = 'Extend actual';

    public function __construct(
        private SubscriptionsRepository $subscriptionsRepository
    ) {
    }

    public function getStartTime(ActiveRow $user, ActiveRow $subscriptionType): Extension
    {
        $actualSubscription = $this->subscriptionsRepository->actualUserSubscription($user->id);
        if ($actualSubscription) {
            return new Extension($actualSubscription->end_time, $subscriptionType->id === $actualSubscription->subscription_type_id);
        }
        return new Extension($this->getNow());
    }
}
