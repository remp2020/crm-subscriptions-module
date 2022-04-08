<?php

namespace Crm\SubscriptionsModule\Extension;

use Crm\ApplicationModule\NowTrait;
use Crm\SubscriptionsModule\Repository\ContentAccessRepository;
use Crm\SubscriptionsModule\Repository\SubscriptionsRepository;
use Nette\Database\Table\ActiveRow;
use Nette\Utils\DateTime;

class ExtendSameContentAccess implements ExtensionInterface
{
    use NowTrait;

    public const METHOD_CODE = 'extend_same_content_access';
    public const METHOD_NAME = 'Extend same content access';

    private ContentAccessRepository $contentAccessRepository;

    private SubscriptionsRepository $subscriptionsRepository;

    public function __construct(
        ContentAccessRepository $contentAccessRepository,
        SubscriptionsRepository $subscriptionsRepository
    ) {
        $this->contentAccessRepository = $contentAccessRepository;
        $this->subscriptionsRepository = $subscriptionsRepository;
    }

    /**
     * @param ActiveRow $user
     * @param ActiveRow $subscriptionType
     * @return Extension
     * @throws \Exception
     */
    public function getStartTime(ActiveRow $user, ActiveRow $subscriptionType): Extension
    {
        $startTimeForSameContentAccess = $this->getStartTimeForSameContentAccess($user, $subscriptionType);
        if ($startTimeForSameContentAccess !== null) {
            return new Extension($startTimeForSameContentAccess, true);
        }

        return new Extension($this->getNow());
    }

    /**
     * Helper method used to get start time following subscription of same content access.
     *
     * @return \DateTime|null Returns null if not subscription with same content access was found
     */
    public function getStartTimeForSameContentAccess(ActiveRow $user, ActiveRow $subscriptionType): ?\DateTime
    {
        $requiredContentAccesses = $this->contentAccessRepository->allForSubscriptionType($subscriptionType)
            ->fetchAssoc('id');

        $lifetimeThreshold = new DateTime('+ 30 years');
        $userSubscriptions = $this->subscriptionsRepository->userSubscriptions($user->id)
            ->where(
                'subscription_type:subscription_type_content_access.content_access_id',
                array_keys($requiredContentAccesses)
            )
            ->where('end_time > ?', $this->getNow())
            ->where('end_time <', $lifetimeThreshold)
            ->fetchAll();

        foreach ($userSubscriptions as $subscription) {
            $subscriptionContentAccesses = $this->contentAccessRepository
                ->allForSubscriptionType($subscription->subscription_type)
                ->fetchAssoc('id');

            $matchedCount = count(
                array_intersect(
                    array_keys($requiredContentAccesses),
                    array_keys($subscriptionContentAccesses)
                )
            );
            if ($matchedCount === count($requiredContentAccesses)
                && $matchedCount === count($subscriptionContentAccesses)
            ) {
                return $subscription->end_time;
            }
        }

        return null;
    }
}
