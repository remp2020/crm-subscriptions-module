<?php

namespace Crm\SubscriptionsModule\Extension;

use Crm\ApplicationModule\NowTrait;
use Crm\SubscriptionsModule\Repository\SubscriptionsRepository;
use Nette\Database\Table\IRow;

class ExtendSameActualExtension implements ExtensionInterface
{
    use NowTrait;

    public const METHOD_CODE = 'extend_same_actual';

    public const METHOD_NAME = 'Extend same actual';

    private $subscriptionsRepository;

    public function __construct(
        SubscriptionsRepository $subscriptionsRepository
    ) {
        $this->subscriptionsRepository = $subscriptionsRepository;
    }

    public function getStartTime(IRow $user, IRow $subscriptionType)
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
