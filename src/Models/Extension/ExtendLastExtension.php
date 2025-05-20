<?php

namespace Crm\SubscriptionsModule\Models\Extension;

use Crm\ApplicationModule\Models\NowTrait;
use Crm\SubscriptionsModule\Repositories\SubscriptionsRepository;
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

    private ?array $ignoreSubscriptionsWithContentAccess = null;


    public function __construct(
        private SubscriptionsRepository $subscriptionsRepository,
    ) {
    }

    public function setIgnoreSubscriptionsWithContentAccess(...$contentAccessNames): void
    {
        $this->ignoreSubscriptionsWithContentAccess = $contentAccessNames;
    }

    public function getStartTime(ActiveRow $user, ActiveRow $subscriptionType, ?ActiveRow $address = null): Extension
    {
        $q = $this->subscriptionsRepository->lastActiveUserSubscription($user->id);
        if ($this->ignoreSubscriptionsWithContentAccess) {
            $q->where(
                'subscription_type:subscription_type_content_access.content_access.name NOT IN (?)',
                $this->ignoreSubscriptionsWithContentAccess,
            );
        }
        $lastSubscription = $q->fetch();
        if ($lastSubscription) {
            return new Extension($lastSubscription->end_time, true);
        }

        return new Extension($this->getNow());
    }
}
