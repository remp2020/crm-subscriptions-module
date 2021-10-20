<?php

namespace Crm\SubscriptionsModule\Repository;

use Crm\ApplicationModule\DataProvider\DataProviderManager;
use Crm\ApplicationModule\Repository;
use Crm\SubscriptionsModule\DataProvider\CanUpdateSubscriptionTypeItemDataProviderInterface;
use Exception;
use Nette\Caching\Storage;
use Nette\Database\Explorer;
use Nette\Database\Table\ActiveRow;
use Nette\Utils\DateTime;

class SubscriptionTypeItemsRepository extends Repository
{
    protected $tableName = 'subscription_type_items';

    private $dataProviderManager;

    public function __construct(
        Explorer $database,
        Storage $cacheStorage = null,
        DataProviderManager $dataProviderManager
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

    public function update(ActiveRow &$row, $data)
    {
        if (!($this->canBeUpdated($row))) {
            throw new Exception('Subscription type item ' . $row->id . ' cannot be updated');
        }

        $data['updated_at'] = new DateTime();
        return parent::update($row, $data);
    }

    final public function exists(ActiveRow $subscriptionType, string $name)
    {
        return $this->getTable()->where(['subscription_type_id' => $subscriptionType->id, 'name' => $name])->count('*');
    }

    final public function subscriptionTypeItems(ActiveRow $subscriptionType)
    {
        return $this->getTable()->where(['subscription_type_id' => $subscriptionType->id])->order('sorting ASC');
    }

    private function getNextSorting(ActiveRow $subscriptionType)
    {
        $item = $this->getTable()->where(['subscription_type_id' => $subscriptionType->id])->order('sorting DESC')->limit(1)->fetch();
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
