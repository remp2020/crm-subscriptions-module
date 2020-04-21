<?php

namespace Crm\SubscriptionsModule\Forms;

use Crm\SubscriptionsModule\Repository\SubscriptionTypeItemsRepository;
use Crm\SubscriptionsModule\Repository\SubscriptionTypesMetaRepository;
use Crm\SubscriptionsModule\Repository\SubscriptionTypesRepository;
use Nette\Application\UI\Form;
use Nette\Database\IRow;
use Nette\Database\UniqueConstraintViolationException;
use Nette\Localization\ITranslator;
use Nette\SmartObject;
use Nette\Utils\DateTime;
use Tomaj\Form\Renderer\BootstrapRenderer;

class SubscriptionTypeMetaFormFactory
{
    use SmartObject;

    private $subscriptionTypesRepository;

    private $subscriptionTypesMetaRepository;

    private $translator;

    public $onSave;

    public $onError;

    public function __construct(
        SubscriptionTypesRepository $subscriptionTypesRepository,
        SubscriptionTypesMetaRepository $subscriptionTypesMetaRepository,
        ITranslator $translator
    ) {
        $this->subscriptionTypesRepository = $subscriptionTypesRepository;
        $this->subscriptionTypesMetaRepository = $subscriptionTypesMetaRepository;
        $this->translator = $translator;
    }

    public function create(IRow $subscriptionType): Form
    {
        $form = new Form();
        $form->setTranslator($this->translator);
        $form->setRenderer(new BootstrapRenderer());
        $form->getElementPrototype()->addAttributes(['class' => 'ajax']);

        $form->addText('key', 'subscriptions.admin.subscription_types_meta.form.key.label')
            ->setRequired('subscriptions.admin.subscription_types_meta.form.key.required');
        $form->addText('value', 'subscriptions.admin.subscription_types_meta.form.value.label')
            ->setRequired('subscriptions.admin.subscription_types_meta.form.value.required');
        $form->addHidden('subscription_type_id', $subscriptionType['id'])
            ->setHtmlId('subscription_type_id');
        $form->addHidden('subscription_type_meta_id')
            ->setHtmlId('subscription_type_meta_id');
        $form->addSubmit('submit', 'subscriptions.admin.subscription_types_meta.form.submit');

        $form->onSuccess[] = function ($form, $values) {
            if ($values['subscription_type_meta_id']) {
                $meta = $this->subscriptionTypesMetaRepository->find($values['subscription_type_meta_id']);
                if (!$meta) {
                    $form->addError('subscriptions.admin.subscription_types_meta.error.internal');
                    $this->onError->__invoke();
                    return;
                }

                try {
                    $this->subscriptionTypesMetaRepository->update($meta, [
                        'key' => $values['key'],
                        'value' => $values['value'],
                        'updated_at' => new DateTime()
                    ]);
                } catch (UniqueConstraintViolationException $e) {
                    $form->addError('subscriptions.admin.subscription_types_meta.error.duplicate');
                    $this->onError->__invoke();
                    return;
                }
            } else {
                $subscriptionType = $this->subscriptionTypesRepository->find($values['subscription_type_id']);
                if (!$subscriptionType) {
                    $form->addError('subscriptions.admin.subscription_types_meta.error.internal');
                    $this->onError->__invoke();
                    return;
                }

                try {
                    $meta = $this->subscriptionTypesMetaRepository->add(
                        $subscriptionType,
                        $values['key'],
                        $values['value']
                    );
                } catch (UniqueConstraintViolationException $e) {
                    $form->addError('subscriptions.admin.subscription_types_meta.error.duplicate');
                    $this->onError->__invoke();
                    return;
                }
            }
            $this->onSave->__invoke($meta);
        };

        return $form;
    }
}
