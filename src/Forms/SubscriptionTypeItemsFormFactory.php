<?php

namespace Crm\SubscriptionsModule\Forms;

use Crm\SubscriptionsModule\Repository\SubscriptionTypeItemsRepository;
use Crm\SubscriptionsModule\Repository\SubscriptionTypesRepository;
use Nette\Application\UI\Form;
use Nette\Localization\ITranslator;
use Tomaj\Form\Renderer\BootstrapRenderer;

class SubscriptionTypeItemsFormFactory
{
    private $subscriptionTypesRepository;

    private $subscriptionTypeItemsRepository;

    private $translator;

    public $onSave;

    public function __construct(
        SubscriptionTypesRepository $subscriptionTypesRepository,
        SubscriptionTypeItemsRepository $subscriptionTypeItemsRepository,
        ITranslator $translator
    ) {
        $this->subscriptionTypesRepository = $subscriptionTypesRepository;
        $this->subscriptionTypeItemsRepository = $subscriptionTypeItemsRepository;
        $this->translator = $translator;
    }

    public function create(): Form
    {
        $form = new Form;

        $form->setRenderer(new BootstrapRenderer());
        $form->setTranslator($this->translator);
        $form->addProtection();
        $form->getElementPrototype()->addAttributes(['class' => 'ajax']);

        $form->addGroup();

        $form->addText('name', 'subscriptions.data.subscription_type_items.fields.name')
            ->setAttribute('placeholder', 'subscriptions.data.subscription_type_items.placeholder.name')
            ->setRequired('subscriptions.data.subscription_type_items.required.name');

        $form->addText('amount', 'subscriptions.data.subscription_type_items.fields.amount')
            ->setAttribute('placeholder', 'subscriptions.data.subscription_type_items.placeholder.amount')
            ->setRequired('subscriptions.data.subscription_type_items.required.amount')
            ->addRule(Form::FLOAT);

        $form->addInteger('vat', 'subscriptions.data.subscription_type_items.fields.vat')
            ->setAttribute('placeholder', 'subscriptions.data.subscription_type_items.placeholder.vat')
            ->setRequired('subscriptions.data.subscription_type_items.required.vat');

        $form->addHidden('subscription_type_id')
            ->setHtmlId('subscription_type_id');

        $form->addHidden('subscription_type_item_id')
            ->setHtmlId('subscription_type_item_id');

        $form->addSubmit('send', 'system.save')
            ->getControlPrototype()
            ->setName('button')
            ->setHtml('<i class="fa fa-save"></i> ' . $this->translator->translate('system.save'));

        $form->onSuccess[] = [$this, 'formSucceeded'];

        return $form;
    }

    public function formSucceeded($form, $values)
    {
        if ($values['subscription_type_item_id']) {
            $item = $this->subscriptionTypeItemsRepository->find($values['subscription_type_item_id']);
            $this->subscriptionTypeItemsRepository->update(
                $item,
                [
                    'name' => $values['name'],
                    'amount' => $values['amount'],
                    'vat' => $values['vat'],
                ]
            );
        } else {
            $subscriptionType = $this->subscriptionTypesRepository->find($values['subscription_type_id']);
            $item = $this->subscriptionTypeItemsRepository->add(
                $subscriptionType,
                $values['name'],
                $values['amount'],
                $values['vat']
            );
        }

        $this->onSave->__invoke($item);
    }
}
