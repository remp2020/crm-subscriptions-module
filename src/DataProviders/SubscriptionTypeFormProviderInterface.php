<?php

namespace Crm\SubscriptionsModule\DataProviders;

use Crm\ApplicationModule\DataProvider\DataProviderInterface;
use Nette\Application\UI\Form;
use Nette\Utils\ArrayHash;

interface SubscriptionTypeFormProviderInterface extends DataProviderInterface
{
    public function provide(array $params): Form;

    public function formSucceeded(Form $form, ArrayHash $values);
}
