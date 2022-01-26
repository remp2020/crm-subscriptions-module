<?php

namespace Crm\SubscriptionsModule\DataProvider;

use Crm\ApplicationModule\DataProvider\DataProviderInterface;
use Nette\Application\UI\Form;

interface SubscriptionTypeFormProviderInterface extends DataProviderInterface
{
    public function provide(array $params): Form;

    public function formSucceeded($form, $values);
}
