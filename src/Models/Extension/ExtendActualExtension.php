<?php

namespace Crm\SubscriptionsModule\Extension;

use Crm\ApplicationModule\NowTrait;
use Crm\SubscriptionsModule\Repository\SubscriptionsRepository;
use Nette\Database\Table\IRow;

class ExtendActualExtension implements ExtensionInterface
{
    use NowTrait;

    public const METHOD_CODE = 'extend_actual';

    public const METHOD_NAME = 'Extend actual';

    private $subscriptionsRepository;

    public function __construct(
        SubscriptionsRepository $subscriptionsRepository
    ) {
        $this->subscriptionsRepository = $subscriptionsRepository;
    }

    public function getStartTime(IRow $user, IRow $subscriptionType)
    {
        $actualSubscription = $this->subscriptionsRepository->actualUserSubscription($user->id);
        if ($actualSubscription) {
            return new Extension($actualSubscription->end_time, $subscriptionType->id === $actualSubscription->subscription_type_id);
        }
        return new Extension($this->getNow());
    }
}
