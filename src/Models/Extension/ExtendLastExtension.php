<?php

namespace Crm\SubscriptionsModule\Extension;

use Crm\ApplicationModule\NowTrait;
use Crm\SubscriptionsModule\Repository\SubscriptionsRepository;
use Nette\Database\Table\ActiveRow;

/**
 * Extends:
 * - last subscription;
 * - or starts immediately.
 */
class ExtendLastExtension implements ExtensionInterface
{
    use NowTrait;

    public const METHOD_CODE = 'extend_last_subscription';
    public const METHOD_NAME = 'Extend last subscription';

    public function __construct(
        private SubscriptionsRepository $subscriptionsRepository
    ) {
    }

    public function getStartTime(ActiveRow $user, ActiveRow $subscriptionType): Extension
    {
        $lastSubscription = $this->subscriptionsRepository->lastActiveUserSubscription($user->id)->fetch();
        if ($lastSubscription) {
            return new Extension($lastSubscription->end_time, true);
        }

        return new Extension($this->getNow());
    }
}
