<?php

namespace Crm\SubscriptionsModule\Repository;

use Crm\ApplicationModule\Repository;
use Nette\Database\Table\IRow;
use Nette\Database\Table\Selection;
use Nette\Utils\DateTime;

class SubscriptionTypesMetaRepository extends Repository
{
    protected $tableName = 'subscription_types_meta';

    final public function add(IRow $subscriptionType, string $key, $value, int $sorting = 100)
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

    final public function getMeta(IRow $subscriptionType, string $key): Selection
    {
        return $this->getTable()->where(['subscription_type_id' => $subscriptionType->id, 'key' => $key]);
    }

    final public function subscriptionTypeMeta(IRow $subscriptionType): array
    {
        return $this->getTable()->where([
            'subscription_type_id' => $subscriptionType->id,
        ])->order('sorting ASC')->fetchPairs('key', 'value');
    }

    final public function subscriptionTypeMetaRows(IRow $subscriptionType)
    {
        return $this->getTable()->where(['subscription_type_id' => $subscriptionType['id']])->order('key ASC');
    }

    final public function exists(IRow $subscriptionType, string $key): bool
    {
        return $this->getMeta($subscriptionType, $key)->count('*') > 0;
    }

    final public function setMeta(IRow $subscriptionType, string $key, $value): IRow
    {
        if ($meta = $this->getMeta($subscriptionType, $key)->fetch()) {
            $this->update($meta, ['value' => $value]);
            return $meta;
        } else {
            return $this->add($subscriptionType, $key, $value);
        }
    }

    final public function getMetaValue(IRow $subscriptionType, string $key): string
    {
        return $this->getMeta($subscriptionType, $key)->fetchField('value');
    }

    final public function removeMeta($subscriptionTypeId, $key)
    {
        return $this->getTable()->where(['subscription_type_id' => $subscriptionTypeId, 'key' => $key])->delete();
    }
}
