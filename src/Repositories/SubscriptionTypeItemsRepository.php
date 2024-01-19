<?php

namespace Crm\SubscriptionsModule\Repositories;

use Crm\ApplicationModule\Models\DataProvider\DataProviderManager;
use Crm\ApplicationModule\Models\Database\Repository;
use Crm\SubscriptionsModule\DataProviders\CanUpdateSubscriptionTypeItemDataProviderInterface;
use Exception;
use Nette\Caching\Storage;
use Nette\Database\Explorer;
use Nette\Database\Table\ActiveRow;
use Nette\Database\Table\GroupedSelection;
use Nette\Utils\DateTime;

class SubscriptionTypeItemsRepository extends Repository
{
    protected $tableName = 'subscription_type_items';

    private $dataProviderManager;

    public function __construct(
        Explorer $database,
        DataProviderManager $dataProviderManager,
        Storage $cacheStorage = null,
    ) {
        parent::__construct($database, $cacheStorage);
        $this->dataProviderManager = $dataProviderManager;
    }

    final public function add(ActiveRow $subscriptionType, string $name, float $amount, int $vat, int $sorting = null)
    {
        return $this->getTable()->insert([
            'subscription_type_id' => $subscriptionType->id,
            'name' => $name,
            'amount' => $amount,
            'vat' => $vat,
            'sorting' => $sorting ? $sorting : $this->getNextSorting($subscriptionType),
            'created_at' => new DateTime(),
            'updated_at' => new DateTime(),
        ]);
    }

    public function update(ActiveRow &$row, $data, bool $force = false)
    {
        if (!$force && !$this->canBeUpdated($row)) {
            throw new Exception('Subscription type item ' . $row->id . ' cannot be updated');
        }

        $data['updated_at'] = new DateTime();
        return parent::update($row, $data);
    }

    final public function exists(ActiveRow $subscriptionType, string $name): int
    {
        return $this->getItemsForSubscriptionType($subscriptionType)
            ->where('name', $name)
            ->count('*');
    }

    final public function subscriptionTypeItems(ActiveRow $subscriptionType)
    {
        return $this->getItemsForSubscriptionType($subscriptionType)->order('sorting ASC');
    }

    final public function softDelete(ActiveRow $subscriptionTypeItem, bool $force = false): bool
    {
        return $this->update($subscriptionTypeItem, [
            'deleted_at' => new DateTime(),
            'updated_at' => new DateTime(),
        ], $force);
    }

    final public function getItemsForSubscriptionType(ActiveRow $subscriptionType): GroupedSelection
    {
        return $subscriptionType->related('subscription_type_items')
            ->where('deleted_at', null);
    }

    private function getNextSorting(ActiveRow $subscriptionType)
    {
        $item = $this->getItemsForSubscriptionType($subscriptionType)->order('sorting DESC')->limit(1)->fetch();
        if (!$item) {
            return 100;
        }
        return $item->sorting + 100;
    }

    private function canBeUpdated($subscriptionTypeItem): bool
    {
        /** @var CanUpdateSubscriptionTypeItemDataProviderInterface[] $providers */
        $providers = $this->dataProviderManager->getProviders('subscriptions.subscription_type_items.update', CanUpdateSubscriptionTypeItemDataProviderInterface::class);
        foreach ($providers as $sorting => $provider) {
            if (!($provider->provide(['subscriptionTypeItem' => $subscriptionTypeItem]))) {
                return false;
            }
        }

        return true;
    }
}
