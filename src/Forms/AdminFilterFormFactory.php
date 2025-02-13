<?php

namespace Crm\SubscriptionsModule\Forms;

use Crm\ApplicationModule\UI\Form;
use Crm\SubscriptionsModule\Repositories\ContentAccessRepository;
use Crm\SubscriptionsModule\Repositories\SubscriptionTypeTagsRepository;
use Nette\Localization\Translator;
use Tomaj\Form\Renderer\BootstrapRenderer;

class AdminFilterFormFactory
{
    public $onFilter;

    public $onCancel;

    public function __construct(
        private Translator $translator,
        private ContentAccessRepository $contentAccessRepository,
        private SubscriptionTypeTagsRepository $subscriptionTypeTagsRepository,
    ) {
    }

    public function create()
    {
        $form = new Form;
        $form->setRenderer(new BootstrapRenderer());
        $form->setTranslator($this->translator);

        $mainGroup = $form->addGroup('main')->setOption('label', null);

        $contentAccessPairs = $this->contentAccessRepository->all()->fetchPairs('name', 'description');

        $form->addText('name', 'subscriptions.admin.admin_filter_form.name.label');
        $form->addText('code', 'subscriptions.admin.admin_filter_form.code.label');
        $form->addMultiSelect('content_access', 'subscriptions.admin.admin_filter_form.content_access.label', $contentAccessPairs)
            ->getControlPrototype()->addAttributes(['class' => 'select2']);

        $tags = $this->subscriptionTypeTagsRepository
            ->tagsSortedByOccurrences();

        $form->addMultiSelect('tag', 'subscriptions.admin.admin_filter_form.tag.label', $tags)
            ->getControlPrototype()->addAttributes(['class' => 'select2']);

        $collapseGroup = $form->addGroup('collapse')
            ->setOption('container', 'div class="collapse"')
            ->setOption('label', null)
            ->setOption('id', 'formCollapse');

        $form->addText('price_from', 'subscriptions.admin.admin_filter_form.price_from.label')
            ->setHtmlAttribute('type', 'number');

        $form->addText('price_to', 'subscriptions.admin.admin_filter_form.price_to.label')
            ->setHtmlAttribute('type', 'number');

        $form->addInteger('length_from', 'subscriptions.admin.admin_filter_form.length_from.label');

        $form->addInteger('length_to', 'subscriptions.admin.admin_filter_form.length_to.label');

        $form->addCheckbox('default', 'subscriptions.admin.admin_filter_form.default.label');

        $buttonGroup = $form->addGroup('button')->setOption('label', null);

        $form->addSubmit('send', 'subscriptions.admin.admin_filter_form.submit')
            ->getControlPrototype()
            ->setName('button')
            ->setHtml('<i class="fa fa-filter"></i> ' . $this->translator->translate('subscriptions.admin.admin_filter_form.submit'));

        $form->addSubmit('cancel', 'subscriptions.admin.admin_filter_form.cancel_filter')->onClick[] = function () use ($form) {
            $emptyDefaults = array_fill_keys(array_keys((array) $form->getComponents()), null);
            $this->onCancel->__invoke($emptyDefaults);
        };

        $form->addButton('more')
            ->setHtmlAttribute('data-toggle', 'collapse')
            ->setHtmlAttribute('data-target', '#formCollapse')
            ->setHtmlAttribute('class', 'btn btn-info')
            ->getControlPrototype()
            ->setName('button')
            ->setHtml('<i class="fas fa-caret-down"></i> ' . $this->translator->translate('subscriptions.admin.admin_filter_form.more'));

        $form->onSuccess[] = [$this, 'formSucceeded'];
        return $form;
    }

    public function formSucceeded($form, $values)
    {
        $this->onFilter->__invoke((array) $values);
    }
}
