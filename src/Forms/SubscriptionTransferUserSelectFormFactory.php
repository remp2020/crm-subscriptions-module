<?php

namespace Crm\SubscriptionsModule\Forms;

use Contributte\Translation\Translator;
use Crm\ApplicationModule\UI\Form;
use Nette\Forms\Controls\SelectBox;
use Tomaj\Form\Renderer\BootstrapRenderer;

class SubscriptionTransferUserSelectFormFactory
{
    private const FIELD_USER_ID = 'user_id';

    /**
     * @var callable(int $userId): void
     */
    public $onSelect;

    public function __construct(
        private readonly Translator $translator,
    ) {
    }

    public function create(string $dataSourceUrl): Form
    {
        $form = new Form();

        $form->setRenderer(new BootstrapRenderer());
        $form->setTranslator($this->translator);
        $form->addProtection();

        $subscriptionTypeSelect = $form->addSelect(
            self::FIELD_USER_ID,
            'subscriptions.admin.subscriptions_transfer.select_user.user',
        )->setPrompt("--");

        $subscriptionTypeSelect->getControlPrototype()
            ->addAttributes([
                'class' => 'select2',
                'data-ajax--method' => 'POST',
                'data-ajax--url' => $dataSourceUrl,
                'data-ajax--delay' => '500',
                'data-allow-clear' => 'false',
                'data-minimum-input-length' => 1,
                'data-placeholder' => $this->translator->translate('subscriptions.admin.subscriptions_transfer.select_user.user_placeholder'),
            ]);

        $form->addSubmit('send', 'subscriptions.admin.subscriptions_transfer.select_user.continue_to_summary_button');

        $form->onSuccess[] = [$this, 'formSucceeded'];

        return $form;
    }

    public function formSucceeded(Form $form, $values): void
    {
        /** @var SelectBox $userIdField */
        $userIdField = $form[self::FIELD_USER_ID]; // when value is not present in $data, it is not possible to use $values[self::FIELD_USER_ID] directly
        ($this->onSelect)($userIdField->getRawValue());
    }
}
