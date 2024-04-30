<?php

namespace Crm\SubscriptionsModule\Repositories;

use Crm\ApplicationModule\Models\Database\Repository;
use Nette\Caching\Storage;
use Nette\Database\Explorer;
use Nette\Database\Table\ActiveRow;
use Nette\Database\Table\Selection;
use Nette\Utils\DateTime;

class ContentAccessRepository extends Repository
{
    protected $tableName = 'content_access';

    public function __construct(
        private SubscriptionTypeContentAccessRepository $subscriptionTypeContentAccessRepository,
        Explorer $database,
        Storage $cacheStorage = null
    ) {
        parent::__construct($database, $cacheStorage);
    }

    final public function all(): Selection
    {
        return $this->getTable()->order('sorting');
    }

    final public function add($name, $description, $class = '', $sorting = 100)
    {
        return $this->getTable()->insert([
            'name' => $name,
            'description' => $description,
            'class' => $class,
            'sorting' => $sorting,
            'created_at' => new DateTime(),
            'updated_at' => new DateTime(),
        ]);
    }

    final public function exists($name)
    {
        return $this->getTable()->where(['name' => $name])->count('*') > 0;
    }

    final public function hasAccess(ActiveRow $subscriptionType, $name)
    {
        return $this->getDatabase()->table('subscription_type_content_access')
            ->where([
                'subscription_type_id' => $subscriptionType->id,
                'content_access.name' => $name
            ])
            ->count('*') > 0;
    }

    final public function hasOneOfAccess(ActiveRow $subscriptionType, array $names)
    {
        return $this->getDatabase()->table('subscription_type_content_access')
                ->where([
                    'subscription_type_id' => $subscriptionType->id,
                    'content_access.name IN (?)' => $names
                ])
                ->count('*') > 0;
    }

    final public function allForSubscriptionType(ActiveRow $subscriptionType): Selection
    {
        return $this->getTable()
            ->where([
                ':subscription_type_content_access.subscription_type_id' => $subscriptionType->id,
            ])
            ->order('sorting');
    }

    final public function addAccess(ActiveRow $subscriptionType, $name)
    {
        $contentAccess = $this->getByName($name);
        $this->subscriptionTypeContentAccessRepository->add(
            subscriptionType: $subscriptionType,
            contentAccess: $contentAccess,
        );
    }

    final public function removeAccess(ActiveRow $subscriptionType, $name)
    {
        $contentAccess = $this->getByName($name);
        $this->subscriptionTypeContentAccessRepository->remove(
            subscriptionType: $subscriptionType,
            contentAccess: $contentAccess,
        );
    }

    final public function getId($name)
    {
        return $this->getTable()->select('id')->where(['name' => $name])->limit(1)->fetch()->id;
    }

    /**
     * @param $contentAccess
     * @param DateTime $startTime
     * @param DateTime $endTime
     * @return Selection
     */
    final public function usersWithAccessActiveBetween($contentAccess, DateTime $startTime, DateTime $endTime)
    {
        return $this->database->table('users')
            ->where(':subscriptions.subscription_type:subscription_type_content_access.content_access_id = ?', $contentAccess->id)
            ->where(':access_tokens.last_used_at > ?', $startTime)
            ->where(':access_tokens.last_used_at < ?', $endTime)
            ->where('users.active = ?', true)
            ->group('users.id');
    }

    final public function getByName($name)
    {
        return $this->getTable()->where('name', $name)->fetch();
    }
}
