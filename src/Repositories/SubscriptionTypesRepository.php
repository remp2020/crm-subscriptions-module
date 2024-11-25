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

    final public function findByCode($code): ?ActiveRow
    {
        return $this->getTable()->where('code', $code)->fetch();
    }

    final public function findDefaultForLengthAndContentAccesses(int $length, string ...$contentAccess)
    {
        $selection = $this->getTable()
            ->where([
                'default' => true,
                'length' => $length,
                ':subscription_type_content_access.content_access.name' => $contentAccess,
            ])
            ->group('subscription_types.id')
            ->having('COUNT(:subscription_type_content_access.id) = ?', count($contentAccess))
            ->order('price');

        // If there are subscription types with content access A/B and A/B/C, and we only require default for A/B,
        // this matches every subscription type with extra content access ("C") and excludes it from the query later.
        $excludedSubscriptionTypes = $this->getTable()
            ->select('subscription_types.id AS id')
            ->where(':subscription_type_content_access.content_access.name NOT IN (?)', $contentAccess)
            ->group('subscription_types.id')
            ->fetchAll();

        if (count($excludedSubscriptionTypes)) {
            $selection->where(['subscription_types.id NOT IN' => $excludedSubscriptionTypes]);
        }

        return $selection->fetch();
    }
}
