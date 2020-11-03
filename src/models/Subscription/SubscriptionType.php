<?php

namespace Crm\SubscriptionsModule\Subscription;

use Nette\Database\Table\ActiveRow;
use Nette\Utils\Json;

class SubscriptionType
{
    public static function getPairs($subscriptionTypes): array
    {
        $subscriptionTypePairs = [];
        foreach ($subscriptionTypes as $st) {
            $subscriptionTypePairs[$st->id] = "$st->name <small>({$st->code})</small>";
        }
        return $subscriptionTypePairs;
    }

    public static function getItems($subscriptionTypes): array
    {
        $subscriptionPairs = [];
        /** @var ActiveRow $st */
        foreach ($subscriptionTypes as $st) {
            $subscriptionPairs[$st->id] = [
                'price' => $st->price,
                'items' => [],
            ];
            foreach ($st->related('subscription_type_items') as $item) {
                $subscriptionPairs[$st->id]['items'][] = [
                    'name' => $item->name,
                    'amount' => $item->amount,
                    'vat' => $item->vat,
                    'meta' => Json::encode(array_merge($item->related('subscription_type_item_meta')->fetchPairs('key', 'value'), ['subscription_type_item_id' => $item->id]))
                ];
            }
        }
        return $subscriptionPairs;
    }
}
