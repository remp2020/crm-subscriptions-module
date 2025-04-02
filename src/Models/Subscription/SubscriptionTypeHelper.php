<?php

namespace Crm\SubscriptionsModule\Models\Subscription;

use Crm\SubscriptionsModule\Repositories\SubscriptionTypeItemsRepository;
use Crm\SubscriptionsModule\Repositories\SubscriptionsRepository;
use Nette\Database\Table\ActiveRow;

class SubscriptionTypeHelper
{

    public function __construct(
        private SubscriptionsRepository $subscriptionsRepository,
        private SubscriptionTypeItemsRepository $subscriptionTypeItemsRepository
    ) {
    }

    public function getItems($subscriptionTypes): array
    {
        $subscriptionPairs = [];
        /** @var ActiveRow $st */
        foreach ($subscriptionTypes as $st) {
            $subscriptionPairs[$st->id] = [
                'price' => $st->price,
                'items' => [],
            ];
            foreach ($this->subscriptionTypeItemsRepository->getItemsForSubscriptionType($st) as $item) {
                $subscriptionPairs[$st->id]['items'][] = [
                    'subscription_type_item_id' => $item->id,
                    'name' => $item->name,
                    'amount' => $item->amount,
                    'vat' => $item->vat,
                    'meta' => $item->related('subscription_type_item_meta')->fetchPairs('key', 'value')
                ];
            }
        }
        return $subscriptionPairs;
    }

    public function validateSubscriptionTypeCounts(ActiveRow $subscriptionType, ActiveRow $user): bool
    {
        if (!$subscriptionType->limit_per_user) {
            return true;
        }

        $userSubscriptionsTypesCount = $this->subscriptionsRepository->userSubscriptionTypesCounts(
            $user->id,
            [
                $subscriptionType->id
            ]
        );

        if (!isset($userSubscriptionsTypesCount[$subscriptionType->id])) {
            return true;
        }
        if ($subscriptionType->limit_per_user <= $userSubscriptionsTypesCount[$subscriptionType->id]) {
            return false;
        }
        return true;
    }
}
