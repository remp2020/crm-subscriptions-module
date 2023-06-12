<?php

namespace Crm\SubscriptionsModule\Repository;

use Crm\ApplicationModule\Repository;
use Crm\SubscriptionsModule\Events\ContentAccessAssignedEvent;
use Crm\SubscriptionsModule\Events\ContentAccessUnassignedEvent;
use League\Event\Emitter;
use Nette\Caching\Storage;
use Nette\Database\Explorer;
use Nette\Database\Table\ActiveRow;
use Nette\Utils\DateTime;

class SubscriptionTypeContentAccessRepository extends Repository
{
    protected $tableName = 'subscription_type_content_access';

    public function __construct(
        private Emitter $emitter,
        Explorer $database,
        Storage $cacheStorage = null,
    ) {
        parent::__construct($database, $cacheStorage);
    }

    public function add(ActiveRow $subscriptionType, ActiveRow $contentAccess)
    {
        $this->getTable()->insert([
            'subscription_type_id' => $subscriptionType->id,
            'content_access_id' => $contentAccess->id,
            'created_at' => new DateTime(),
        ]);

        $this->emitter->emit(new ContentAccessAssignedEvent($subscriptionType, $contentAccess));
    }

    public function remove(ActiveRow $subscriptionType, ActiveRow $contentAccess)
    {
        $this->getTable()->where([
            'subscription_type_id' => $subscriptionType->id,
            'content_access_id' => $contentAccess->id,
        ])->delete();

        $this->emitter->emit(new ContentAccessUnassignedEvent($subscriptionType, $contentAccess));
    }
}
