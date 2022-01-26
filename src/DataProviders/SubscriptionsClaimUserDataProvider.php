<?php

namespace Crm\SubscriptionsModule\DataProvider;

use Crm\ApplicationModule\DataProvider\DataProviderException;
use Crm\SubscriptionsModule\Repository\SubscriptionsRepository;
use Crm\UsersModule\User\ClaimUserDataProviderInterface;

class SubscriptionsClaimUserDataProvider implements ClaimUserDataProviderInterface
{
    private $subscriptionsRepository;

    public function __construct(
        SubscriptionsRepository $subscriptionsRepository
    ) {
        $this->subscriptionsRepository = $subscriptionsRepository;
    }

    public function provide(array $params): void
    {
        if (!isset($params['unclaimedUser'])) {
            throw new DataProviderException('unclaimedUser param missing');
        }
        if (!isset($params['loggedUser'])) {
            throw new DataProviderException('loggedUser param missing');
        }

        $unclaimedUserSubscriptions = $this->subscriptionsRepository->userSubscriptions($params['unclaimedUser']->id)->fetchAll();
        foreach ($unclaimedUserSubscriptions as $unclaimedUserSubscription) {
            $this->subscriptionsRepository->update($unclaimedUserSubscription, ['user_id' => $params['loggedUser']->id]);
        }
    }
}
