<?php

namespace Crm\SubscriptionsModule\Repository;

use Crm\ApplicationModule\Repository;
use Crm\ApplicationModule\Repository\AuditLogRepository;
use Nette\Database\Context;
use Nette\Database\Table\IRow;
use Nette\Database\Table\Selection;

class SubscriptionTypesRepository extends Repository
{
    protected $tableName = 'subscription_types';

    public function __construct(Context $database, AuditLogRepository $auditLogRepository)
    {
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

    final public function update(IRow &$row, $data)
    {
        $data['modified_at'] = new \DateTime();
        return parent::update($row, $data);
    }

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
