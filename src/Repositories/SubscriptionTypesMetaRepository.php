<?php

namespace Crm\SubscriptionsModule\Repositories;

use Crm\ApplicationModule\Models\Database\Repository;
use Nette\Database\Table\ActiveRow;
use Nette\Database\Table\Selection;
use Nette\Utils\DateTime;

class SubscriptionTypesMetaRepository extends Repository
{
    protected $tableName = 'subscription_types_meta';

    final public function add(ActiveRow $subscriptionType, string $key, $value, int $sorting = 100)
    {
        return $this->getTable()->insert([
            'subscription_type_id' => $subscriptionType->id,
            'key' => $key,
            'value' => (string) $value,
            'sorting' => $sorting,
            'created_at' => new DateTime(),
            'updated_at' => new DateTime(),
        ]);
    }

    final public function getByKey(string $key): Selection
    {
        return $this->getTable()->where(['key' => $key]);
    }

    final public function getByKeyAndValue(string $key, $value): Selection
    {
        return $this->getTable()->where([
            'key' => $key,
            'value' => $value,
        ]);
    }

    final public function getMeta(ActiveRow $subscriptionType, string $key): Selection
    {
        return $subscriptionType->related('subscription_types_meta')->where(['key' => $key]);
    }

    final public function subscriptionTypeMeta(ActiveRow $subscriptionType): array
    {
        return $this->getTable()->where([
            'subscription_type_id' => $subscriptionType->id,
        ])->order('sorting ASC')->fetchPairs('key', 'value');
    }

    final public function getAllBySubscriptionType(ActiveRow $subscriptionType)
    {
        return $subscriptionType->related('subscription_types_meta');
    }

    final public function exists(ActiveRow $subscriptionType, string $key): bool
    {
        return $this->getMeta($subscriptionType, $key)->count('*') > 0;
    }

    final public function setMeta(ActiveRow $subscriptionType, string $key, $value): ActiveRow
    {
        if ($meta = $this->getMeta($subscriptionType, $key)->fetch()) {
            $this->update($meta, ['value' => $value]);
            return $meta;
        } else {
            return $this->add($subscriptionType, $key, $value);
        }
    }

    final public function getMetaValue(ActiveRow $subscriptionType, string $key): ?string
    {
        return $this->getMeta($subscriptionType, $key)->fetchField('value');
    }

    final public function removeMeta($subscriptionTypeId, $key)
    {
        return $this->getTable()->where(['subscription_type_id' => $subscriptionTypeId, 'key' => $key])->delete();
    }
}
