<?php


namespace Crm\SubscriptionsModule\DataProviders;

use Crm\ApplicationModule\Models\DataProvider\DataProviderInterface;
use Crm\ApplicationModule\UI\Form;

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
