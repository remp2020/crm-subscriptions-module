<?php

namespace Crm\SubscriptionsModule\DataProviders;

use Crm\ApplicationModule\Models\DataProvider\DataProviderInterface;
use Nette\Application\UI\Form;
use Nette\Database\Table\ActiveRow;
use Nette\Utils\ArrayHash;

interface SubscriptionTransferDataProviderInterface extends DataProviderInterface
{
    /**
     * @param array{subscription: ActiveRow, form: Form} $params
     */
    public function provide(array $params): void;

    public function transfer(ActiveRow $subscription, ActiveRow $userToTransferTo, ArrayHash $formData): void;

    public function isTransferable(ActiveRow $subscription): bool;
}
