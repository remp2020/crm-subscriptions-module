<?php

namespace Crm\SubscriptionsModule\Forms;

use Contributte\Translation\Translator;
use Contributte\Translation\Wrappers\Message;
use Crm\ApplicationModule\Hermes\HermesMessage;
use Crm\ApplicationModule\UI\Form;
use Crm\PaymentsModule\Forms\Controls\SubscriptionTypesSelectItemsBuilder;
use Crm\SubscriptionsModule\Repositories\SubscriptionTypesRepository;
use Crm\SubscriptionsModule\Repositories\SubscriptionsRepository;
use Crm\UsersModule\Models\Auth\UserManager;
use Crm\UsersModule\Models\Email\EmailValidator;
use DateInterval;
use Nette\Utils\DateTime;
use Tomaj\Form\Renderer\BootstrapRenderer;
use Tomaj\Hermes\Emitter;

class SubscriptionsGeneratorFormFactory
{
    const REGISTRATIONS = 'registrations';
    const NEWLY_REGISTERED = 'newly_registered';
    const INACTIVE = 'inactive';
    const ACTIVE = 'active';
    const SKIPPED = 'skipped';

    public $onSubmit;

    public $onCreate;

    public function __construct(
        private readonly UserManager $userManager,
        private readonly SubscriptionTypesRepository $subscriptionTypesRepository,
        private readonly Translator $translator,
        private readonly SubscriptionsRepository $subscriptionsRepository,
        private readonly EmailValidator $emailValidator,
        private readonly Emitter $emitter,
        private readonly SubscriptionTypesSelectItemsBuilder $subscriptionTypesSelectItemsBuilder,
    ) {
    }

    /**
     * @return Form
     */
    public function create()
    {
        $defaults = [
            'type' => SubscriptionsRepository::TYPE_FREE,
            'create_users' => true,
            'user_groups' => [
                self::NEWLY_REGISTERED,
                self::INACTIVE,
            ],
        ];

        $form = new Form;

        $form->setRenderer(new BootstrapRenderer());
        $form->setTranslator($this->translator);
        $form->addProtection();

        $form->addGroup('subscriptions.menu.subscriptions');

        $subscriptionTypes = $this->subscriptionTypesRepository->getAllActive()->fetchAll();
        $subscriptionType = $form->addSelect(
            'subscription_type',
            'subscriptions.admin.subscription_generator.field.subscription_type',
            $this->subscriptionTypesSelectItemsBuilder->buildWithDescription($subscriptionTypes)
        )
            ->setPrompt("subscriptions.admin.subscription_generator.prompt.subscription_type")
            ->setRequired("subscriptions.admin.subscription_generator.required.subscription_type");
        $subscriptionType->getControlPrototype()->addAttributes(['class' => 'select2']);

        $form->addText('start_time', 'subscriptions.data.subscriptions.fields.start_time')
            ->setHtmlAttribute('placeholder', 'subscriptions.data.subscriptions.placeholder.start_time')
            ->setOption('description', 'subscriptions.admin.subscription_generator.description.start_time')
            ->setHtmlAttribute('class', 'flatpickr');

        $form->addText('end_time', 'subscriptions.data.subscriptions.fields.end_time')
            ->setHtmlAttribute('placeholder', 'subscriptions.data.subscriptions.placeholder.end_time')
            ->setOption('description', 'subscriptions.admin.subscription_generator.description.end_time')
            ->setHtmlAttribute('class', 'flatpickr');

        $form->addCheckbox('is_paid', 'subscriptions.data.subscriptions.fields.is_paid');

        $form->addSelect('type', 'subscriptions.data.subscriptions.fields.type', $this->subscriptionsRepository->availableTypes())
            ->setOption('description', 'subscriptions.admin.subscription_generator.description.type');

        $form->addText('note', 'subscriptions.data.subscriptions.fields.note')
            ->setHtmlAttribute('placeholder', 'subscriptions.data.subscriptions.placeholder.note');

        $form->addGroup('subscriptions.admin.subscription_generator.group.users');

        $form->addTextArea('emails', 'subscriptions.admin.subscription_generator.field.emails')
            ->setHtmlAttribute('rows', 20)
            ->setRequired('subscriptions.admin.subscription_generator.required.emails')
            ->setHtmlAttribute('placeholder', 'subscriptions.admin.subscription_generator.placeholder.emails')
            ->setOption('description', 'subscriptions.admin.subscription_generator.description.emails');

        $form->addCheckbox('create_users', 'subscriptions.admin.subscription_generator.field.create_users')
            ->setOption('description', 'subscriptions.admin.subscription_generator.description.create_users');

        $form->addCheckboxList('user_groups', 'subscriptions.admin.subscription_generator.field.user_groups', [
            'newly_registered' => 'subscriptions.admin.subscription_generator.field.newly_registered',
            'inactive' => 'subscriptions.admin.subscription_generator.field.inactive',
            'active' => 'subscriptions.admin.subscription_generator.field.active',
        ])
            ->setOption('description', 'subscriptions.admin.subscription_generator.description.user_groups');

        $form->addCheckbox('generate', 'subscriptions.admin.subscription_generator.form.generate')
            ->setOption('description', 'subscriptions.admin.subscription_generator.description.generate');

        $form->addSubmit('submit', 'subscriptions.admin.subscription_generator.form.send')
            ->getControlPrototype()
            ->setName('button')
            ->setHtml('<i class="fa fa-save"></i> ' . $this->translator->translate('subscriptions.admin.subscription_generator.form.send'));

        $form->setDefaults($defaults);

        $form->onSuccess[] = [$this, 'formSucceeded'];

        return $form;
    }

    public function formSucceeded(Form $form, $values)
    {
        $subscriptionType = $this->subscriptionTypesRepository->find($values['subscription_type']);

        $startTime = DateTime::from(strtotime($values['start_time']));
        $endTime = DateTime::from(strtotime($values['end_time']));
        if (!$values['end_time']) {
            $endTime = clone $startTime;
            $endTime->add(new DateInterval("P{$subscriptionType->length}D"));
        }

        $stats = [
            self::REGISTRATIONS => 0,
            self::NEWLY_REGISTERED => 0,
            self::INACTIVE => 0,
            self::ACTIVE => 0,
            self::SKIPPED => 0,
        ];

        $emails = [];
        foreach (explode("\n", $values->emails) as $email) {
            $email = trim($email);
            if (empty($email)) {
                continue;
            }
            if (!$this->emailValidator->isValid($email)) {
                $form['emails']->addError(new Message('subscriptions.admin.subscription_generator.errors.invalid_email', ['email' => $email]));
            }
            $emails[] = $email;
        }

        $payload = [
            'register' => [],
            'subscribe' => [],
        ];

        foreach ($emails as $email) {
            $user = $this->userManager->loadUserByEmail($email);

            // newly registered
            if (!$user) {
                if (!$values->create_users) {
                    // user doesn't exist and we don't want create new users
                    continue;
                }

                $payload['register'][] = [
                    'email' => $email,
                    'send_email' => true,
                    'source' => 'subscriptiongenerator',
                    'check_email' => false,
                ];

                $stats[self::REGISTRATIONS] += 1;

                if (!in_array(self::NEWLY_REGISTERED, $values->user_groups, true)) {
                    $stats[self::SKIPPED] += 1;
                    // we don't want to create subscription for newly registered, halting here
                    continue;
                }

                $subscriptionParams = [
                    'subscription_type_id' => $subscriptionType->id,
                    'email' => $email,
                    'type' => $values['type'],
                    'start_time' => $startTime->format(DATE_RFC3339),
                    'end_time' => $endTime->format(DATE_RFC3339),
                    'is_paid' => $values['is_paid'],
                ];

                if (!empty($values['note'])) {
                    $subscriptionParams['note'] = $values['note'];
                }

                $payload['subscribe'][] = $subscriptionParams;
                $stats[self::NEWLY_REGISTERED] += 1;

                // newly registered scenario handled completely
                continue;
            }

            // already registered
            $actualSubscription = $this->subscriptionsRepository->actualUserSubscription($user->id);

            if ($actualSubscription && !in_array(self::ACTIVE, $values->user_groups, true)) {
                // we don't want to create subscription for active subscribers, halting here
                $stats[self::SKIPPED] += 1;
                continue;
            }
            if (!$actualSubscription && !in_array(self::INACTIVE, $values->user_groups, true)) {
                // we don't want to create subscription for inactive subscribers, halting here
                $stats[self::SKIPPED] += 1;
                continue;
            }

            $actualSubscription ? $stats[self::ACTIVE] += 1 : $stats[self::INACTIVE] += 1;

            $subscriptionParams = [
                'subscription_type_id' => $subscriptionType->id,
                'email' => $user->email,
                'type' => $values['type'],
                'start_time' => $startTime->format(DATE_RFC3339),
                'end_time' => $endTime->format(DATE_RFC3339),
                'is_paid' => $values['is_paid'],
            ];

            if (!empty($values['note'])) {
                $subscriptionParams['note'] = $values['note'];
            }

            $payload['subscribe'][] = $subscriptionParams;
        }

        $messages = [];
        $type = $values->generate ? 'info' : 'warning';
        $messages += [
            [
                'text' => $this->translator->translate('subscriptions.admin.subscription_generator.messages.registrations', $stats[self::REGISTRATIONS]),
                'type' => $type
            ],
            [
                'text' => $this->translator->translate('subscriptions.admin.subscription_generator.messages.newly_registered', $stats[self::NEWLY_REGISTERED]),
                'type' => $type
            ],
            [
                'text' => $this->translator->translate('subscriptions.admin.subscription_generator.messages.inactive', $stats[self::INACTIVE]),
                'type' => $type
            ],
            [
                'text' => $this->translator->translate('subscriptions.admin.subscription_generator.messages.active', $stats[self::ACTIVE]),
                'type' => $type,
            ],
            [
                'text' => $this->translator->translate('subscriptions.admin.subscription_generator.messages.skipped', $stats[self::SKIPPED]),
                'type' => 'warning',
            ],
        ];

        if ($values->generate) {
            $this->emitter->emit(new HermesMessage('generate-subscription', $payload));
        }

        ($this->onSubmit)($messages);
    }
}
