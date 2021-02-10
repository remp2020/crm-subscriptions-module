<?php

namespace Crm\SubscriptionsModule\Extension;

use Crm\ApplicationModule\NowTrait;
use Crm\SubscriptionsModule\Repository\SubscriptionsRepository;
use Nette\Database\Table\IRow;

class ExtendSameTypeExtension implements ExtensionInterface
{
    use NowTrait;

    public const METHOD_CODE = 'extend_same_type';
    public const METHOD_NAME = 'Extend same type';

    private $subscriptionsRepository;

    public function __construct(
        SubscriptionsRepository $subscriptionsRepository
    ) {
        $this->subscriptionsRepository = $subscriptionsRepository;
    }

    public function getStartTime(IRow $user, IRow $subscriptionType)
    {
        $lastSubscriptionTypeSubscription = $this->subscriptionsRepository->userSubscriptions($user->id)
            ->where('subscription_type_id', $subscriptionType->id)
            ->where('end_time > ?', $this->getNow())
            ->fetch();
        if ($lastSubscriptionTypeSubscription) {
            return new Extension($lastSubscriptionTypeSubscription->end_time, true);
        }

        $actualSubscription = $this->subscriptionsRepository->actualUserSubscription($user->id);
        if ($actualSubscription) {
            return new Extension($actualSubscription->end_time, $subscriptionType->id === $actualSubscription->subscription_type_id);
        }
        return new Extension($this->getNow());
    }
}
