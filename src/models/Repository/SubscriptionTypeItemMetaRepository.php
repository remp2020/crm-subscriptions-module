<?php

namespace Crm\SubscriptionsModule\Repository;

use Crm\ApplicationModule\Repository;
use Nette\Database\Table\ActiveRow;
use Nette\Database\Table\IRow;
use Nette\Database\Table\Selection;
use Nette\Utils\DateTime;

class SubscriptionTypeItemMetaRepository extends Repository
{
    protected $tableName = 'subscription_type_item_meta';

    final public function add(ActiveRow $subscriptionTypeItem, string $key, string $value)
    {
        return $this->insert([
            'subscription_type_item_id' => $subscriptionTypeItem->id,
            'key' => $key,
            'value' => $value,
            'created_at' => new DateTime(),
            'updated_at' => new DateTime()
        ]);
    }

    final public function findBySubscriptionTypeItem(IRow $subscriptionTypeItem): Selection
    {
        return $this->getTable()->where(['subscription_type_item_id' => $subscriptionTypeItem->id]);
    }

    final public function findBySubscriptionTypeItemAndKey(IRow $subscriptionTypeItem, string $key): Selection
    {
        return $this->findBySubscriptionTypeItem($subscriptionTypeItem)->where(['key' => $key]);
    }

    final public function exists(IRow $subscriptionTypeItem, string $key): bool
    {
        return $this->findBySubscriptionTypeItemAndKey($subscriptionTypeItem, $key)->count('*') > 0;
    }

    final public function subscriptionTypeItemsHaveMeta(IRow $subscriptionType): bool
    {
        $subscriptionTypeItemIds = $subscriptionType->related('subscription_type_items')->fetchPairs(null, 'id');
        return $this->getTable()->where('subscription_type_item_id', $subscriptionTypeItemIds)->count('*') > 0;
    }
}
