<?php

namespace Crm\SubscriptionsModule\DataProvider;

use Crm\ApplicationModule\DataProvider\DataProviderException;
use Crm\ApplicationModule\Selection;
use Crm\UsersModule\DataProvider\FilterAbusiveUserFormDataProviderInterface;
use Nette\Application\UI\Form;
use Nette\Utils\DateTime;

class FilterAbusiveUserFormDataProvider implements FilterAbusiveUserFormDataProviderInterface
{
    public function provide(array $params)
    {
        if (!isset($params['form'])) {
            throw new DataProviderException('missing [form] within data provider params');
        }
        if (!($params['form'] instanceof Form)) {
            throw new DataProviderException('invalid type of provided form: ' . get_class($params['form']));
        }
        if (!isset($params['params'])) {
            throw new DataProviderException('missing [form] within data provider params');
        }

        $form = $params['form'];
        $container = $form->addContainer('additional');
        $container->addText('subscriptionTo', 'subscriptions.data_provider.abusive_user.form.subscription_date_to')
            ->setAttribute('class', 'form-control flatpickr text flatpickr-input input active');

        $form->setDefaults($params['params']);

        return $form;
    }

    public function filter(Selection $selection, array $params): Selection
    {
        if (isset($params['additional']['subscriptionTo'])) {
            $selection->where([
                ':subscriptions.start_time <= ?' => new DateTime(),
                ':subscriptions.end_time >= ?' => $params['additional']['subscriptionTo'],
            ]);
        }

        return $selection;
    }
}
