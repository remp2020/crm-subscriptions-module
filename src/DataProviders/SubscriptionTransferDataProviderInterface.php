<?php

namespace Crm\SubscriptionsModule\DataProviders;

use Crm\ApplicationModule\Models\DataProvider\DataProviderInterface;
use Crm\ApplicationModule\UI\Form;
use Nette\Database\Table\ActiveRow;
use Nette\Utils\ArrayHash;

interface SubscriptionTransferDataProviderInterface extends DataProviderInterface
{
    public const META_KEY_TRANSFERRED_FROM_USER = 'subscription_transfer_from';

    /**
     * @param array{subscription: ActiveRow, form: Form} $params
     */
    public function provide(array $params): void;

    public function transfer(ActiveRow $subscription, ActiveRow $userToTransferTo, ArrayHash $formData): void;

    public function isTransferable(ActiveRow $subscription): bool;
}
