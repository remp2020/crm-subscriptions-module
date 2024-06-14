<?php

namespace Crm\SubscriptionsModule\DataProviders;

use Crm\ApplicationModule\Models\DataProvider\DataProviderException;
use Crm\ApplicationModule\Models\NowTrait;
use Crm\PaymentsModule\Forms\Controls\SubscriptionTypesSelectItemsBuilder;
use Crm\SubscriptionsModule\Repositories\SubscriptionTypesRepository;
use Crm\UsersModule\DataProviders\FilterUsersFormDataProviderInterface;
use Nette\Application\UI\Form;
use Nette\Database\Table\Selection;
use Nette\Localization\Translator;

class FilterUsersFormDataProvider implements FilterUsersFormDataProviderInterface
{
    use NowTrait;

    public function __construct(
        private readonly SubscriptionTypesRepository $subscriptionTypesRepository,
        private readonly Translator $translator,
        private readonly SubscriptionTypesSelectItemsBuilder $subscriptionTypesSelectItemsBuilder,
    ) {
    }

    public function provide(array $params): Form
    {
        if (!isset($params['form'])) {
            throw new DataProviderException('missing [form] within data provider params');
        }
        if (!($params['form'] instanceof Form)) {
            throw new DataProviderException('invalid type of provided form: ' . get_class($params['form']));
        }

        if (!isset($params['formData'])) {
            throw new DataProviderException('missing [formData] within data provider params');
        }
        if (!is_array($params['formData'])) {
            throw new DataProviderException('invalid type of provided formData: ' . get_class($params['formData']));
        }

        $form = $params['form'];
        $formData = $params['formData'];

        $subscriptionTypes = $this->subscriptionTypesRepository->all()->fetchAll();
        $subscriptionType = $form->addSelect(
            'subscription_type',
            $this->translator->translate('subscriptions.admin.filter_users.subscription_type'),
            $this->subscriptionTypesSelectItemsBuilder->buildWithDescription($subscriptionTypes)
        )->setPrompt('--');
        $subscriptionType->getControlPrototype()->addAttributes(['class' => 'select2']);

        $form->addCheckbox('actual_subscription', $this->translator->translate('subscriptions.admin.filter_users.actual_subscription'));

        $form->setDefaults([
            'subscription_type' => $this->getSubscriptionType($formData),
            'actual_subscription' => $this->getActualSubscription($formData)
        ]);

        return $form;
    }

    public function filter(Selection $selection, array $formData): Selection
    {
        $actualSubscription = $this->getActualSubscription($formData);
        if ($actualSubscription && $actualSubscription === '1') {
            $selection
                ->where(':subscriptions.start_time < ?', $this->getNow())
                ->where(':subscriptions.end_time > ?', $this->getNow());
        }
        if ($this->getSubscriptionType($formData)) {
            $selection->where(':subscriptions.subscription_type_id = ?', (int)$formData['subscription_type']);
        }

        return $selection;
    }

    private function getSubscriptionType($formData)
    {
        return $formData['subscription_type'] ?? null;
    }

    private function getActualSubscription($formData)
    {
        return $formData['actual_subscription'] ?? null;
    }
}
