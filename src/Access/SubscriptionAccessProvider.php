<?php

namespace Crm\SubscriptionsModule\Access;

use Crm\ApplicationModule\Access\ProviderInterface;
use Crm\SubscriptionsModule\Repositories\ContentAccessRepository;
use Crm\SubscriptionsModule\Repositories\SubscriptionsRepository;

class SubscriptionAccessProvider implements ProviderInterface
{
    private array $accesses;

    public function __construct(
        private SubscriptionsRepository $subscriptionsRepository,
        private ContentAccessRepository $contentAccessRepository
    ) {
    }

    public function hasAccess($userId, $access)
    {
        return $this->subscriptionsRepository->hasAccess($userId, $access);
    }

    public function available($access)
    {
        if (!isset($this->accesses)) {
            $this->accesses = $this->contentAccessRepository->all()->fetchPairs(null, 'name');
        }
        return in_array($access, $this->accesses, true);
    }
}
