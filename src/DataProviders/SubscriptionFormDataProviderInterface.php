<?php


namespace Crm\SubscriptionsModule\DataProviders;

use Crm\ApplicationModule\DataProvider\DataProviderInterface;
use Nette\Application\UI\Form;

interface SubscriptionFormDataProviderInterface extends DataProviderInterface
{
    public const PATH = 'subscriptions.dataprovider.subscription_form';

    /**
     * @param array $params {
     *   @type \Nette\Application\UI\Form $form
     * }
     */
    public function provide(array $params): Form;
}
