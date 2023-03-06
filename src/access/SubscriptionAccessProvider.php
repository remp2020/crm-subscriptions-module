<?php

namespace Crm\SubscriptionsModule\Access;

use Crm\ApplicationModule\Access\ProviderInterface;
use Crm\SubscriptionsModule\Repository\ContentAccessRepository;
use Crm\SubscriptionsModule\Repository\SubscriptionsRepository;

class SubscriptionAccessProvider implements ProviderInterface
{
    private $subscriptionsRepository;

    private $contentAccessRepository;

    private $accesses = null;

    public function __construct(
        SubscriptionsRepository $subscriptionsRepository,
        ContentAccessRepository $contentAccessRepository
    ) {
        $this->subscriptionsRepository = $subscriptionsRepository;
        $this->contentAccessRepository = $contentAccessRepository;
    }

    public function hasAccess($userId, $access)
    {
        return $this->subscriptionsRepository->hasAccess($userId, $access);
    }

    public function available($access)
    {
        if (!$this->accesses) {
            $this->accesses = $this->contentAccessRepository->all()->fetchPairs(null, 'name');
        }
        return in_array($access, $this->accesses, true);
    }
}
