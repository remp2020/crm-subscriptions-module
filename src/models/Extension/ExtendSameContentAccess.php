<?php

namespace Crm\SubscriptionsModule\Extension;

use Crm\ApplicationModule\NowTrait;
use Crm\SubscriptionsModule\Repository\ContentAccessRepository as ContentAccessRepositoryAlias;
use Crm\SubscriptionsModule\Repository\SubscriptionsRepository;
use Nette\Database\Table\IRow;
use Nette\Utils\DateTime;

class ExtendSameContentAccess implements ExtensionInterface
{
    use NowTrait;

    public const METHOD_CODE = 'extend_same_content_access';

    public const METHOD_NAME = 'Extend same content access';

    private $subscriptionsRepository;

    private $contentAccessRepository;

    public function __construct(
        SubscriptionsRepository $subscriptionsRepository,
        ContentAccessRepositoryAlias $contentAccessRepository
    ) {
        $this->subscriptionsRepository = $subscriptionsRepository;
        $this->contentAccessRepository = $contentAccessRepository;
    }

    /**
     * @param IRow $user
     * @param IRow $subscriptionType
     * @return Extension
     * @throws \Exception
     */
    public function getStartTime(IRow $user, IRow $subscriptionType): Extension
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
                return new Extension($subscription->end_time, true);
            }
        }

        return new Extension($this->getNow());
    }
}
