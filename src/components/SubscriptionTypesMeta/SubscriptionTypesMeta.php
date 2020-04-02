<?php

namespace Crm\SubscriptionsModule\Components;

use Crm\ApplicationModule\Widget\WidgetInterface;
use Crm\SubscriptionsModule\Repository\SubscriptionTypesMetaRepository;
use Crm\SubscriptionsModule\Repository\SubscriptionTypesRepository;
use Nette\Application\UI\Control;
use Nette\Application\UI\Form;
use Nette\Database\IRow;
use Nette\Localization\ITranslator;
use Tomaj\Form\Renderer\BootstrapRenderer;

class SubscriptionTypesMeta extends Control implements WidgetInterface
{
    private $templateName = 'subscription_types_meta.latte';

    private $subscriptionTypesMetaRepository;

    private $subscriptionTypesRepository;

    private $translator;

    private $subscriptionType;

    private $totalCount = null;

    public function __construct(
        SubscriptionTypesMetaRepository $subscriptionTypesMetaRepository,
        SubscriptionTypesRepository $subscriptionTypesRepository,
        ITranslator $translator
    ) {
        parent::__construct();
        $this->subscriptionTypesMetaRepository = $subscriptionTypesMetaRepository;
        $this->subscriptionTypesRepository = $subscriptionTypesRepository;
        $this->translator = $translator;
    }

    public function header($id = '')
    {
        $header = $this->translator->translate('subscriptions.admin.subscription_types_meta.header');
        if ($id) {
            $header .= ' <small>(' . $this->totalCount($id) . ')</small>';
        }
        return $header;
    }

    public function identifier()
    {
        return 'subscriptiontypesmeta';
    }

    public function render(IRow $subscriptionType)
    {
        $this->subscriptionType = $subscriptionType;
        $this->template->meta = $this->subscriptionTypesMetaRepository->subscriptionTypeMetaRows($this->subscriptionType)->order('key ASC');
        $this->template->subscriptionType = $subscriptionType;
        $this->template->setFile(__DIR__ . '/' . $this->templateName);
        $this->template->render();
    }

    public function handleDelete($subscriptionTypeId, $key)
    {
        $this->subscriptionTypesMetaRepository->removeMeta($subscriptionTypeId, $key);
        $this->presenter->flashMessage($this->translator->translate('subscriptions.admin.subscription_types_meta.value_removed'));
        $this->presenter->redirect('this');
    }

    protected function createComponentMetaForm()
    {
        $form = new Form();
        $form->setTranslator($this->translator);
        $form->setRenderer(new BootstrapRenderer());
        $form->getElementPrototype()->addAttributes(['class' => 'ajax']);

        $form->addText('key', 'subscriptions.admin.subscription_types_meta.form.key.label')
            ->setRequired('subscriptions.admin.subscription_types_meta.form.key.required');
        $form->addText('value', 'subscriptions.admin.subscription_types_meta.form.value.label')
            ->setRequired('subscriptions.admin.subscription_types_meta.form.value.required');
        $form->addHidden('subscription_type_id', $this->subscriptionType['id'])
            ->setHtmlId('subscription_type_id');
        $form->addSubmit('submit', 'subscriptions.admin.subscription_types_meta.form.submit');

        $form->onSuccess[] = function ($form, $values) {
            $this->subscriptionTypesMetaRepository->setMeta(
                $this->subscriptionTypesRepository->find($values['subscription_type_id']),
                $values['key'],
                $values['value']
            );
            $this->presenter->flashMessage($this->translator->translate('subscriptions.admin.subscription_types_meta.value_added'));
            $this->presenter->redirect('SubscriptionTypesAdmin:show', $values['subscription_type_id']);
        };

        return $form;
    }

    private function totalCount($id)
    {
        if ($this->totalCount == null) {
            $this->totalCount = count($this->subscriptionTypesMetaRepository->subscriptionTypeMeta($id));
        }
        return $this->totalCount;
    }
}
