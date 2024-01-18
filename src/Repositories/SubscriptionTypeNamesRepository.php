<?php

namespace Crm\SubscriptionsModule\Repositories;

use Crm\ApplicationModule\Repository;

class SubscriptionTypeNamesRepository extends Repository
{
    protected $tableName = 'subscription_type_names';

    final public function add($type, $sorting)
    {
        return $this->getTable()->insert([
            'type' => $type,
            'sorting' => $sorting,
        ]);
    }

    final public function exists($type)
    {
        return $this->getTable()->where(['type' => $type])->count('*');
    }

    final public function allActive()
    {
        return $this->getTable()
            ->where(['is_active' => true])
            ->order('sorting');
    }
}
