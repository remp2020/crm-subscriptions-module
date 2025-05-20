<?php

namespace Crm\SubscriptionsModule\DataProviders;

use Crm\ApplicationModule\Models\DataProvider\DataProviderException;
use Crm\SubscriptionsModule\Repositories\SubscriptionsRepository;
use Crm\UsersModule\DataProviders\ClaimUserDataProviderInterface;

class SubscriptionsClaimUserDataProvider implements ClaimUserDataProviderInterface
{
    private $subscriptionsRepository;

    public function __construct(
        SubscriptionsRepository $subscriptionsRepository,
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
