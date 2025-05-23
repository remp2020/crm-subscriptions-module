<?php

namespace Crm\SubscriptionsModule\Forms;

use Contributte\Translation\Translator;
use Crm\ApplicationModule\Models\DataProvider\DataProviderManager;
use Crm\ApplicationModule\UI\Form;
use Crm\PaymentsModule\Forms\Controls\SubscriptionTypesSelectItemsBuilder;
use Crm\SubscriptionsModule\DataProviders\SubscriptionFormDataProviderInterface;
use Crm\SubscriptionsModule\Events\SubscriptionPreUpdateEvent;
use Crm\SubscriptionsModule\Models\Length\LengthMethodFactory;
use Crm\SubscriptionsModule\Repositories\SubscriptionTypesRepository;
use Crm\SubscriptionsModule\Repositories\SubscriptionsRepository;
use Crm\UsersModule\Forms\Controls\AddressesSelectItemsBuilder;
use Crm\UsersModule\Repositories\AddressesRepository;
use Crm\UsersModule\Repositories\UsersRepository;
use League\Event\Emitter;
use Nette\Database\Table\ActiveRow;
use Nette\Utils\DateTime;
use Tomaj\Form\Renderer\BootstrapRenderer;

class SubscriptionFormFactory
{
    public $onSave;

    public $onUpdate;

    public function __construct(
        private readonly DataProviderManager $dataProviderManager,
        private readonly SubscriptionsRepository $subscriptionsRepository,
        private readonly SubscriptionTypesRepository $subscriptionTypesRepository,
        private readonly UsersRepository $usersRepository,
        private readonly AddressesRepository $addressesRepository,
        private readonly LengthMethodFactory $lengthMethodFactory,
        private readonly Translator $translator,
        private readonly Emitter $emitter,
        private readonly SubscriptionTypesSelectItemsBuilder $subscriptionTypesSelectItemsBuilder,
        private readonly AddressesSelectItemsBuilder $addressesSelectItemsBuilder,
    ) {
    }

    /**
     * @return Form
     */
    public function create(ActiveRow $user, int $subscriptionId = null)
    {
        $defaults = [];
        $subscription = false;
        if ($subscriptionId != null) {
            $subscription = $this->subscriptionsRepository->find($subscriptionId);
            $defaults = $subscription->toArray();
        }

        $form = new Form;

        $form->setRenderer(new BootstrapRenderer());
        $form->setTranslator($this->translator);
        $form->addProtection();

        $subscriptionTypes = $this->subscriptionTypesRepository->getAllActive()->fetchAll();

        if ($subscription && !$subscription->subscription_type->active) {
            $subscriptionTypes = [$subscription->subscription_type, ...$subscriptionTypes];
        }

        $subscriptionTypeId = $form->addSelect(
            'subscription_type_id',
            'subscriptions.data.subscriptions.fields.subscription_type',
            $this->subscriptionTypesSelectItemsBuilder->buildWithDescription($subscriptionTypes),
        )->setRequired();
        $subscriptionTypeId->getControlPrototype()->addAttributes(['class' => 'select2']);

        if (!$subscription) {
            $subscriptionTypeId->addRule(function ($field, ActiveRow $user) {
                $subscriptionType = $this->subscriptionTypesRepository->find($field->value);
                if (!empty($subscriptionType->limit_per_user) &&
                    $this->subscriptionsRepository->getCount($subscriptionType->id, $user->id) >= $subscriptionType->limit_per_user) {
                    return false;
                }

                return true;
            }, 'subscriptions.data.subscriptions.required.subscription_type_id', $user);
        }

        $subscriptionTypeNames = $this->subscriptionsRepository->activeSubscriptionTypes()->fetchPairs('type', 'type');
        if ($subscription && !in_array($subscription->type, $subscriptionTypeNames, true)) {
            $subscriptionTypeNames[$subscription->type] = $subscription->type;
        }
        $form->addSelect('type', 'subscriptions.data.subscriptions.fields.type', $subscriptionTypeNames);

        $form->addCheckbox('is_paid', 'subscriptions.data.subscriptions.fields.is_paid');

        $form->addText('start_time', 'subscriptions.data.subscriptions.fields.start_time')
            ->setRequired('subscriptions.data.subscriptions.required.start_time')
            ->setHtmlAttribute('placeholder', 'subscriptions.data.subscriptions.placeholder.start_time')
            ->setHtmlAttribute('class', 'flatpickr')
            ->setHtmlAttribute('flatpickr_datetime_seconds', "1");

        $form->addText('end_time', 'subscriptions.data.subscriptions.fields.end_time')
            ->setHtmlAttribute('placeholder', 'subscriptions.data.subscriptions.placeholder.end_time')
            ->setOption('description', 'subscriptions.data.subscriptions.description.end_time')
            ->setHtmlAttribute('class', 'flatpickr')
            ->setHtmlAttribute('flatpickr_datetime_seconds', "1");

        $form->addTextArea('note', 'subscriptions.data.subscriptions.fields.note')
            ->setHtmlAttribute('placeholder', 'subscriptions.data.subscriptions.placeholder.note')
            ->getControlPrototype()
            ->addAttributes(['class' => 'autosize']);

        $form->addSelect(
            'address_id',
            'subscriptions.data.subscriptions.fields.address_id',
            $this->addressesSelectItemsBuilder->buildSimpleWithTypes($user),
        )
            ->setPrompt('--');

        $form->addHidden('user_id', $user->id);
        if ($subscriptionId) {
            $form->addHidden('subscription_id', $subscriptionId);
        }

        /** @var SubscriptionFormDataProviderInterface[] $providers */
        $providers = $this->dataProviderManager->getProviders(
            SubscriptionFormDataProviderInterface::PATH,
            SubscriptionFormDataProviderInterface::class,
        );
        foreach ($providers as $sorting => $provider) {
            $form = $provider->provide(['form' => $form]);
        }

        $form->addSubmit('send', 'system.save')
            ->getControlPrototype()
            ->setName('button')
            ->setHtml('<i class="fa fa-save"></i> ' . $this->translator->translate('system.save'));

        $form->setDefaults($defaults);

        $form->onSuccess[] = [$this, 'formSucceeded'];

        return $form;
    }

    public function formSucceeded(Form $form, $values)
    {
        if ($values['end_time'] == "") {
            $values['end_time'] = null;
        }

        if ($values['end_time'] && strtotime($values['end_time']) < strtotime($values['start_time'])) {
            $form['end_time']->addError($this->translator->translate('subscriptions.data.subscriptions.errors.end_time_before_start_time'));
            return;
        }

        $startTime = DateTime::from(strtotime($values['start_time']));
        $endTime = null;
        if ($values['end_time']) {
            $endTime = DateTime::from(strtotime($values['end_time']));
        }

        if ($values['subscription_type_id'] == 0) {
            $form['subscription_type_id']->addError($this->translator->translate('subscriptions.data.subscriptions.errors.no_subscription_type_id'));
            return;
        }

        $subscriptionType = $this->subscriptionTypesRepository->find($values['subscription_type_id']);
        $user = $this->usersRepository->find($values['user_id']);

        if (isset($values['subscription_id'])) {
            $subscriptionId = $values['subscription_id'];
            unset($values['subscription_id']);

            $values['start_time'] = $startTime;
            $values['end_time'] = $endTime;

            $subscription = $this->subscriptionsRepository->find($subscriptionId);

            if ($values['start_time'] && !$values['end_time']) {
                $lengthMethod = $this->lengthMethodFactory->getExtension($subscriptionType->length_method_id);
                $length = $lengthMethod->getEndTime($values['start_time'], $subscriptionType, false);
                $values['end_time'] = $length->getEndTime();
            }

            if ($form->hasErrors()) {
                return;
            }

            $this->emitter->emit(new SubscriptionPreUpdateEvent($subscription, $form, $values));
            $this->subscriptionsRepository->update($subscription, $values);
            $this->onUpdate->__invoke($subscription);
        } else {
            $address = null;
            if ($values->address_id) {
                $address = $this->addressesRepository->find($values->address_id);
            }

            $subscription = $this->subscriptionsRepository->add(
                $subscriptionType,
                false,
                $values['is_paid'],
                $user,
                $values['type'],
                $startTime,
                $endTime,
                $values['note'],
                $address,
            );
            $this->onSave->__invoke($subscription);
        }
    }
}
