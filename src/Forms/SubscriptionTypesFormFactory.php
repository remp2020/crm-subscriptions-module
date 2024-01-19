<?php

namespace Crm\SubscriptionsModule\Forms;

use Contributte\FormMultiplier\Multiplier;
use Contributte\Translation\Translator;
use Crm\ApplicationModule\Models\DataProvider\DataProviderException;
use Crm\ApplicationModule\Models\DataProvider\DataProviderManager;
use Crm\SubscriptionsModule\DataProviders\SubscriptionTypeFormProviderInterface;
use Crm\SubscriptionsModule\Models\Builder\SubscriptionTypeBuilder;
use Crm\SubscriptionsModule\Models\Subscription\SubscriptionTypeHelper;
use Crm\SubscriptionsModule\Repositories\ContentAccessRepository;
use Crm\SubscriptionsModule\Repositories\SubscriptionExtensionMethodsRepository;
use Crm\SubscriptionsModule\Repositories\SubscriptionLengthMethodsRepository;
use Crm\SubscriptionsModule\Repositories\SubscriptionTypeItemsRepository;
use Crm\SubscriptionsModule\Repositories\SubscriptionTypeTagsRepository;
use Crm\SubscriptionsModule\Repositories\SubscriptionTypesRepository;
use Nette\Application\UI\Form;
use Nette\Forms\Container;
use Nette\Forms\Controls\TextInput;
use Nette\Utils\DateTime;
use Tomaj\Form\Renderer\BootstrapRenderer;

class SubscriptionTypesFormFactory
{
    public $onSave;

    public $onUpdate;

    public function __construct(
        private SubscriptionTypesRepository $subscriptionTypesRepository,
        private SubscriptionTypeItemsRepository $subscriptionTypeItemsRepository,
        private SubscriptionTypeBuilder $subscriptionTypeBuilder,
        private SubscriptionExtensionMethodsRepository $subscriptionExtensionMethodsRepository,
        private SubscriptionLengthMethodsRepository $subscriptionLengthMethodsRepository,
        private ContentAccessRepository $contentAccessRepository,
        private SubscriptionTypeTagsRepository $subscriptionTypeTagsRepository,
        private Translator $translator,
        private DataProviderManager $dataProviderManager,
        private SubscriptionTypeHelper $subscriptionTypeHelper
    ) {
    }

    /**
     * @param $subscriptionTypeId
     * @return Form
     * @throws DataProviderException
     */
    public function create($subscriptionTypeId): Form
    {
        $defaults = [];
        $subscriptionType = null;
        if (isset($subscriptionTypeId)) {
            $subscriptionType = $this->subscriptionTypesRepository->find($subscriptionTypeId);
            $defaults = $subscriptionType->toArray();

            foreach ($subscriptionType->related("subscription_type_content_access") as $subscriptionTypeContentAccess) {
                $defaults[$subscriptionTypeContentAccess->content_access->name] = true;
            }

            $items = $this->subscriptionTypeItemsRepository->subscriptionTypeItems($subscriptionType)->fetchAll();
            foreach ($items as $item) {
                $defaults['items'][] = [
                    'name' => $item->name,
                    'amount' => $item->amount,
                    'vat' => $item->vat,
                ];
            }
        }

        $form = new Form;

        $form->setRenderer(new BootstrapRenderer());
        $form->setTranslator($this->translator);
        $form->addProtection();

        $form->addGroup();

        $form->addText('name', 'subscriptions.data.subscription_types.fields.name')
            ->setRequired('subscriptions.data.subscription_types.required.name')
            ->setHtmlAttribute('placeholder', 'subscriptions.data.subscription_types.placeholder.name')
            ->setOption('description', 'subscriptions.data.subscription_types.description.name');

        $form->addText('code', 'subscriptions.data.subscription_types.fields.code')
            ->setRequired()
            ->setHtmlAttribute('placeholder', 'subscriptions.data.subscription_types.placeholder.code')
            ->addRule(function (TextInput $control) use (&$subscriptionType) {
                $newValue = $control->getValue();
                if ($subscriptionType && $newValue === $subscriptionType->code) {
                    return true;
                }
                return $this->subscriptionTypesRepository->findByCode($newValue) === null;
            }, 'subscriptions.admin.subscription_types.form.validation.code_duplicate');

        $form->addText('user_label', 'subscriptions.data.subscription_types.fields.user_label')
            ->setRequired('subscriptions.data.subscription_types.required.user_label')
            ->setHtmlAttribute('placeholder', 'subscriptions.data.subscription_types.placeholder.user_label')
            ->setOption('description', 'subscriptions.data.subscription_types.description.user_label');

        $form->addTextArea('description', 'subscriptions.data.subscription_types.fields.description');

        $form->addGroup('subscriptions.admin.subscription_types.form.groups.price');

        $form->addText('price', 'subscriptions.data.subscription_types.fields.price')
            ->setRequired('subscriptions.data.subscription_types.required.price')
            ->addRule(Form::FLOAT, 'subscriptions.admin.subscription_types.form.number')
            ->setHtmlAttribute('placeholder', 'subscriptions.data.subscription_types.placeholder.price');


        $types = $this->subscriptionTypeHelper->getPairs($this->subscriptionTypesRepository->all(), true);
        $subscriptionTypeSelect = $form->addSelect(
            'next_subscription_type_id',
            'subscriptions.data.subscription_types.fields.next_subscription_type_id',
            $types
        )->setPrompt("--");
        $subscriptionTypeSelect->getControlPrototype()->addAttributes(['class' => 'select2']);

        $form->addGroup('subscriptions.admin.subscription_types.form.groups.items');

        /** @var Multiplier $items */
        $items = $form->addMultiplier('items', function (Container $container, \Nette\Forms\Form $form) {
            $container->addText('name', 'subscriptions.admin.subscription_types.form.name')
                ->setRequired('subscriptions.admin.subscription_types.form.required')
                ->setHtmlAttribute('placeholder', 'subscriptions.data.subscription_type_items.placeholder.name');

            $container->addText('amount', 'subscriptions.admin.subscription_types.form.amount')
                ->setRequired('subscriptions.admin.subscription_types.form.required')
                ->addRule(Form::FLOAT, 'subscriptions.admin.subscription_types.form.number')
                ->setHtmlAttribute('placeholder', 'subscriptions.data.subscription_type_items.placeholder.amount');

            $container->addText('vat', 'subscriptions.admin.subscription_types.form.vat')
                ->setRequired('subscriptions.admin.subscription_types.form.required')
                ->addRule(Form::FLOAT, 'subscriptions.admin.subscription_types.form.number')
                ->setHtmlAttribute('placeholder', 'subscriptions.data.subscription_type_items.placeholder.vat');
        }, 1, 20);

        $items->addCreateButton('subscriptions.admin.subscription_type_items.add')->setValidationScope([])->addClass('btn btn-sm btn-default');
        $items->addRemoveButton('subscriptions.admin.subscription_type_items.remove')->addClass('btn btn-sm btn-default');

        $form->addGroup('subscriptions.admin.subscription_types.form.groups.length_extension');

        $form->addSelect('extension_method_id', 'subscriptions.data.subscription_types.fields.extension_method_id', $this->subscriptionExtensionMethodsRepository->all()->fetchPairs('method', 'title'))
            ->setRequired();

        $form->addSelect('length_method_id', 'subscriptions.data.subscription_types.fields.length_method_id', $this->subscriptionLengthMethodsRepository->all()->fetchPairs('method', 'title'))
            ->setRequired();

        $form->addText('length', 'subscriptions.data.subscription_types.fields.length')
            ->setRequired('subscriptions.data.subscription_types.required.length')
            ->setHtmlAttribute('placeholder', 'subscriptions.data.subscription_types.placeholder.length');

        $form->addText('extending_length', 'subscriptions.data.subscription_types.fields.extending_length')
            ->setHtmlAttribute('placeholder', 'subscriptions.data.subscription_types.placeholder.extending_length')
            ->addCondition(Form::FILLED)
            ->addRule(Form::INTEGER, 'subscriptions.data.subscription_types.validation.integer');

        $form->addText('fixed_start', 'subscriptions.data.subscription_types.fields.fixed_start')
            ->setHtmlAttribute('placeholder', 'subscriptions.data.subscription_types.placeholder.fixed_start');

        $form->addText('fixed_end', 'subscriptions.data.subscription_types.fields.fixed_end')
            ->setHtmlAttribute('placeholder', 'subscriptions.data.subscription_types.placeholder.fixed_end');

        $form->addGroup('subscriptions.admin.subscription_types.form.groups.content_access');

        $contentAccesses = $this->contentAccessRepository->all();
        foreach ($contentAccesses as $contentAccess) {
            $form->addCheckbox($contentAccess->name, $contentAccess->name . ($contentAccess->description ? " ({$contentAccess->description})" : ''));
        }

        $form->addGroup('subscriptions.admin.subscription_types.form.groups.other');

        $form->addText('recurrent_charge_before', 'subscriptions.data.subscription_types.fields.recurrent_charge_before')
            ->setHtmlType('number');

        $form->addText('limit_per_user', 'subscriptions.data.subscription_types.fields.limit_per_user')
            ->addCondition(Form::FILLED)
            ->addRule(Form::INTEGER, 'subscriptions.data.subscription_types.validation.integer')
            ->addRule(Form::MIN, 'subscriptions.data.subscription_types.validation.minimum.limit_per_user', 1);

        $form->addCheckbox('ask_address', 'subscriptions.data.subscription_types.fields.ask_address');

        $form->addCheckbox('active', 'subscriptions.data.subscription_types.fields.active');
        $form->addCheckbox('visible', 'subscriptions.data.subscription_types.fields.visible');
        $form->addCheckbox('disable_notifications', 'subscriptions.data.subscription_types.fields.disable_notifications');

        $form->addText('sorting', 'subscriptions.data.subscription_types.fields.sorting')
            ->setDefaultValue(10);

        $sortedByOccurrences = $this->subscriptionTypeTagsRepository
            ->tagsSortedByOccurrences();

        $setTags = $this->subscriptionTypeTagsRepository->all()
            ->where(['subscription_type_id' => $subscriptionTypeId])
            ->fetchPairs('tag', 'tag');

        $tagMultiSelect = $form->addMultiSelect('tags', 'subscriptions.data.subscription_types.fields.tag', $sortedByOccurrences)
            ->checkDefaultValue(false)
            ->setDefaultValue($setTags)
            ->getControlPrototype()->addAttributes([
                'class' => 'select2',
                'tags' => 'true',
            ]);

        /** @var SubscriptionTypeFormProviderInterface[] $providers */
        $providers = $this->dataProviderManager->getProviders('subscriptions.dataprovider.subscription_type_form', SubscriptionTypeFormProviderInterface::class);
        foreach ($providers as $sorting => $provider) {
            $form = $provider->provide(array_filter(['form' => $form, 'subscriptionType' => $subscriptionType]));
        }

        $form->addSubmit('send', 'system.save')
            ->getControlPrototype()
            ->setName('button')
            ->setHtml('<i class="fa fa-save"></i> ' . $this->translator->translate('subscriptions.admin.subscription_types.save'));

        if ($subscriptionTypeId) {
            $form->addHidden('subscription_type_id', $subscriptionTypeId);
        }

        $form->setDefaults($defaults);

        $form->onValidate[] = [$this, 'validateForm'];

        $form->onSuccess[] = [$this, 'formSucceeded'];

        return $form;
    }

    public function validateForm(Form $form, $b)
    {
        $this->validateItemsAmount($form);
        $this->validateTagText($form);
    }

    public function validateItemsAmount(Form $form)
    {
        $totalSum = 0;
        $values = $form->getUnsafeValues('array');

        foreach ($values['items'] as $item) {
            $totalSum += (float) $item['amount'];
        }

        if ($totalSum !== (float) $values['price']) {
            $form->addError('subscriptions.admin.subscription_type_items.sum_error');
        }
    }

    public function validateTagText(Form $form)
    {
        // The database currently uses a varchar(255) so we need to check that the string doesn't grow too long
        $tags = $form->getComponent('tags')->getRawValue();
        foreach ($tags as $tag) {
            $tagLen = mb_strlen($tag, 'utf8');
            if ($tagLen > 255) {
                $form->addError('subscriptions.admin.subscription_type_items.tag_len_error');
            };
        }
    }

    public function formSucceeded(Form $form, $values)
    {
        if ($values['limit_per_user'] === '') {
            $values['limit_per_user'] = null;
        }

        if ($values['fixed_start'] === '') {
            $values['fixed_start'] = null;
        } else {
            $values['fixed_start'] = DateTime::from(strtotime($values['fixed_start']));
        }

        if ($values['fixed_end'] === '') {
            $values['fixed_end'] = null;
        } else {
            $values['fixed_end'] = DateTime::from(strtotime($values['fixed_end']));
        }

        if ($values['recurrent_charge_before'] === '') {
            $values['recurrent_charge_before'] = null;
        }

        if ($values['extending_length'] === '') {
            $values['extending_length'] = null;
        }

        // Nette checks if the selected items were originally present in the multi select and if not removes them.
        // In this case, we want to ignore this safety check
        $tags = $form->getComponent('tags')->getRawValue();
        // We have to remove the tags from the $values variable,
        // or this function attempts to insert the tags array into the database
        unset($values['tags']);

        $contentAccesses = $this->contentAccessRepository->all();

        if (isset($values['subscription_type_id'])) {
            $subscriptionType = $this->subscriptionTypesRepository->find($values['subscription_type_id']);
            $this->subscriptionTypeBuilder->processContentTypes($subscriptionType, (array) $values);

            // TODO: remove this once deprecated columns from subscription_type are removed (web, mobile...)
            foreach ($contentAccesses as $contentAccess) {
                if (isset($values[$contentAccess->name])) {
                    unset($values[$contentAccess->name]);
                }
            }

            $items = $values['items'];
            unset($values['items']);

            /** @var SubscriptionTypeFormProviderInterface[] $providers */
            $providers = $this->dataProviderManager->getProviders(
                'subscriptions.dataprovider.subscription_type_form',
                SubscriptionTypeFormProviderInterface::class
            );
            foreach ($providers as $sorting => $provider) {
                [$form, $values] = $provider->formSucceeded($form, $values);
            }
            unset($values['subscription_type_id']); // unset after data provider since it might be used there

            $this->subscriptionTypesRepository->update($subscriptionType, $values);

            foreach ($this->subscriptionTypeItemsRepository->subscriptionTypeItems($subscriptionType) as $subscriptionTypeItem) {
                $this->subscriptionTypeItemsRepository->softDelete($subscriptionTypeItem);
            }
            foreach ($items as $item) {
                $this->subscriptionTypeItemsRepository->add($subscriptionType, $item['name'], $item['amount'], $item['vat']);
            }

            $this->subscriptionTypeTagsRepository->setTagsForSubscriptionType($subscriptionType, $tags);

            $this->onUpdate->__invoke($subscriptionType);
        } else {
            $subscriptionType = $this->subscriptionTypeBuilder->createNew()
                ->setName($values['name'])
                ->setPrice($values['price'])
                ->setLength($values['length'])
                ->setExtensionMethod($values['extension_method_id'])
                ->setLengthMethod($values['length_method_id'])
                ->setExtendingLength($values['extending_length'])
                ->setFixedStart($values['fixed_start'])
                ->setFixedEnd($values['fixed_end'])
                ->setLimitPerUser($values['limit_per_user'])
                ->setUserLabel($values['user_label'])
                ->setSorting($values['sorting'])
                ->setActive($values['active'])
                ->setVisible($values['visible'])
                ->setDescription($values['description'])
                ->setCode($values['code'])
                ->setAskAddress($values['ask_address'])
                ->setDisabledNotifications($values['disable_notifications'])
                ->setRecurrentChargeBefore($values['recurrent_charge_before'])
                ->setNextSubscriptionTypeId($values['next_subscription_type_id']);

            $contentAccesses = $this->contentAccessRepository->all();
            $contentAccessValues = [];
            foreach ($contentAccesses as $contentAccess) {
                if ($values[$contentAccess->name]) {
                    $contentAccessValues[] = $contentAccess->name;
                }
            }
            $subscriptionType->setContentAccessOption(...$contentAccessValues);

            foreach ($values['items'] as $item) {
                $subscriptionType->addSubscriptionTypeItem($item['name'], $item['amount'], $item['vat']);
            }

            $subscriptionType = $subscriptionType->save();

            /** @var SubscriptionTypeFormProviderInterface[] $providers */
            $providers = $this->dataProviderManager->getProviders(
                'subscriptions.dataprovider.subscription_type_form',
                SubscriptionTypeFormProviderInterface::class
            );
            foreach ($providers as $sorting => $provider) {
                [$form, $values] = $provider->formSucceeded($form, $values);
            }

            if (!$subscriptionType) {
                $form['name']->addError(implode("\n", $this->subscriptionTypeBuilder->getErrors()));
            } else {
                $this->subscriptionTypeBuilder->processContentTypes($subscriptionType, (array) $values);
                $this->subscriptionTypeTagsRepository->setTagsForSubscriptionType($subscriptionType, $tags);
                $this->onSave->__invoke($subscriptionType);
            }
        }
    }
}
