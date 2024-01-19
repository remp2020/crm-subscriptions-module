<?php

namespace Crm\SubscriptionsModule\Repositories;

use Crm\ApplicationModule\Models\Database\Repository;
use Crm\ApplicationModule\Repositories\AuditLogRepository;
use Crm\SubscriptionsModule\Events\SubscriptionTypeUpdatedEvent;
use League\Event\Emitter;
use Nette\Database\Explorer;
use Nette\Database\Table\ActiveRow;
use Nette\Database\Table\Selection;

class SubscriptionTypesRepository extends Repository
{
    protected $tableName = 'subscription_types';

    public function __construct(
        Explorer $database,
        AuditLogRepository $auditLogRepository,
        private Emitter $emitter,
    ) {
        parent::__construct($database);
        $this->auditLogRepository = $auditLogRepository;
    }

    final public function getAllActive(): Selection
    {
        return $this->getTable()->where(['active' => true])->order('sorting');
    }

    final public function getAllVisible(): Selection
    {
        return $this->getTable()->where(['active' => true, 'visible' => true])->order('sorting');
    }

    /**
     * @return Selection
     */
    final public function all(): Selection
    {
        return $this->getTable()->order('sorting');
    }

    final public function update(ActiveRow &$row, $data)
    {
        $data['modified_at'] = new \DateTime();
        $result = parent::update($row, $data);
        $this->emitter->emit(new SubscriptionTypeUpdatedEvent($row));
        return $result;
    }

    /**
     * @deprecated Use Crm\SubscriptionsModule\Repository\ContentAccessRepository::hasAccess() instead.
     */
    final public function getPrintSubscriptionTypes()
    {
        return $this->getTable()->where(['print' => 1])->fetchAll();
    }

    final public function exists($code)
    {
        return $this->getTable()->where('code', $code)->count('*') > 0;
    }

    final public function findByCode($code)
    {
        return $this->getTable()->where('code', $code)->fetch();
    }

    final public function findDefaultForContentAccess(string ...$contentAccess)
    {
        return $this->getTable()
            ->where([
                'default' => true,
                ':subscription_type_content_access.content_access.name' => $contentAccess,
            ])
            ->order('price')
            ->fetch();
    }
}
