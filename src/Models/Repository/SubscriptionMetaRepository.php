<?php

namespace Crm\SubscriptionsModule\Repository;

use Crm\ApplicationModule\Repository;
use Nette\Database\Table\IRow;
use Nette\Database\Table\Selection;
use Nette\Utils\DateTime;

class SubscriptionMetaRepository extends Repository
{
    protected $tableName = 'subscriptions_meta';

    final public function add(IRow $subscription, string $key, $value, int $sorting = 100)
    {
        return $this->getTable()->insert([
            'subscription_id' => $subscription->id,
            'key' => $key,
            'value' => (string) $value,
            'sorting' => $sorting,
            'created_at' => new DateTime(),
            'updated_at' => new DateTime(),
        ]);
    }

    final public function getMeta(IRow $subscription, string $key): Selection
    {
        return $this->getTable()->where(['subscription_id' => $subscription->id, 'key' => $key]);
    }

    final public function subscriptionMeta(IRow $subscription): array
    {
        return $this->getTable()->where([
            'subscription_id' => $subscription->id,
        ])->order('sorting ASC')->fetchPairs('key', 'value');
    }

    final public function exists(IRow $subscription, string $key): bool
    {
        return $this->getMeta($subscription, $key)->count('*') > 0;
    }

    final public function setMeta(IRow $subscription, string $key, $value): IRow
    {
        if ($meta = $this->getMeta($subscription, $key)->fetch()) {
            $this->update($meta, ['value' => $value]);
            return $meta;
        } else {
            return $this->add($subscription, $key, $value);
        }
    }

    final public function getMetaValue(IRow $subscription, string $key): string
    {
        return $this->getTable()->where(['subscription_id' => $subscription->id, 'key' => $key])->fetchField('value');
    }

    final public function findSubscriptionBy(string $key, string $value)
    {
        $meta = $this->getTable()->where(['key' => $key, 'value' => $value])->limit(1)->fetch();
        if ($meta) {
            return $meta->subscription;
        }
        return false;
    }

    final public function findBySubscriptionAndKey(IRow $subscription, string $key)
    {
        return $this->getTable()->where([
            'subscription_id' => $subscription->id,
            'key' => $key,
        ])->fetch();
    }
}
