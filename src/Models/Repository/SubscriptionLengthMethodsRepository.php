<?php

namespace Crm\SubscriptionsModule\Repository;

use Crm\ApplicationModule\Repository;
use Nette\Utils\DateTime;

class SubscriptionLengthMethodsRepository extends Repository
{
    protected $tableName = 'subscription_length_methods';

    final public function all()
    {
        return $this->getTable()->order('sorting');
    }

    final public function add(string $method, string $title, string $description, ?int $sorting)
    {
        return $this->insert([
            'method' => $method,
            'title' => $title,
            'description' => $description,
            'sorting' => $sorting,
            'created_at' => new DateTime(),
        ]);
    }

    final public function exists($method)
    {
        return $this->getTable()->where('method', $method)->count('*') > 0;
    }
}
