<?php

namespace Crm\SubscriptionsModule\Forms;

use Contributte\Translation\Translator;
use Crm\ApplicationModule\Models\DataProvider\DataProviderManager;
use Crm\ApplicationModule\Models\Database\ActiveRow;
use Crm\SubscriptionsModule\DataProviders\SubscriptionTransferDataProviderInterface;
use Crm\SubscriptionsModule\Repositories\SubscriptionsRepository;
use Crm\UsersModule\Repositories\UsersRepository;
use Exception;
use Nette\Application\UI\Form;
use Nette\Database\Connection;
use Nette\Utils\ArrayHash;
use Tomaj\Form\Renderer\BootstrapVerticalRenderer;

class SubscriptionTransferConfirmationFormFactory
{
    private const FIELD_SUBSCRIPTION_ID = 'subscription_id';
    private const FIELD_USER_ID_TO_TRANSFER_TO = 'user_id_to_transfer_to';

    /**
     * @var callable(): void|null
     */
    public $onTransfer = null;

    public function __construct(
        private readonly DataProviderManager $dataProviderManager,
        private readonly SubscriptionsRepository $subscriptionsRepository,
        private readonly UsersRepository $usersRepository,
        private readonly Translator $translator,
        private readonly Connection $connection,
    ) {
    }

    public function create(int $subscriptionId, int $userIdToTransferTo): Form
    {
        $subscription = $this->subscriptionsRepository->find($subscriptionId);

        $form = new Form();
        $form->addProtection();
        $form->setTranslator($this->translator);
        $form->setRenderer(new BootstrapVerticalRenderer());

        $form->addHidden(self::FIELD_SUBSCRIPTION_ID, $subscriptionId)
            ->addRule(Form::Integer);

        $form->addHidden(self::FIELD_USER_ID_TO_TRANSFER_TO, $userIdToTransferTo)
            ->addRule(Form::Integer);

        /** @var SubscriptionTransferDataProviderInterface[] $providers */
        $providers = $this->dataProviderManager->getProviders(
            'subscriptions.dataprovider.transfer',
            SubscriptionTransferDataProviderInterface::class
        );
        foreach ($providers as $provider) {
            $provider->provide([
                'form' => $form,
                'subscription' => $subscription,
            ]);
        }

        $form->addSubmit('transfer', 'subscriptions.admin.subscriptions_transfer.summary.transfer_subscription_button')
            ->getControlPrototype()
            ->addAttributes(['class' => 'btn btn-success btn-lg']);

        $form->onSuccess[] = [$this, 'formSucceeded'];

        return $form;
    }

    public function formSucceeded(Form $form, $values): void
    {
        $subscription = $this->subscriptionsRepository->find($values[self::FIELD_SUBSCRIPTION_ID]);
        if (!$subscription) {
            throw new Exception('Subscription not found');
        }

        /** @var SubscriptionTransferDataProviderInterface[] $providers */
        $providers = $this->dataProviderManager->getProviders(
            'subscriptions.dataprovider.transfer',
            SubscriptionTransferDataProviderInterface::class
        );
        if (!$this->isSubscriptionTransferable($providers, $subscription)) {
            $form->addError('subscriptions.admin.subscriptions_transfer.summary.not_transferable_subscription_error');
            return;
        }

        $this->transferSubscription($providers, $subscription, $values);

        if ($this->onTransfer !== null) {
            ($this->onTransfer)();
        }
    }

    private function isSubscriptionTransferable(array $providers, ActiveRow $subscription): bool
    {
        foreach ($providers as $provider) {
            if (!$provider->isTransferable($subscription)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param SubscriptionTransferDataProviderInterface[] $providers
     */
    private function transferSubscription(array $providers, ActiveRow $subscription, ArrayHash $values): void
    {
        $userToTransferTo = $this->usersRepository->find($values[self::FIELD_USER_ID_TO_TRANSFER_TO]);
        if (!$userToTransferTo) {
            throw new Exception('User not found');
        }

        if ($userToTransferTo->id === $subscription->user_id) {
            throw new Exception('User needs to transfer subscription to another user.');
        }

        $this->connection->transaction(function () use ($providers, $subscription, $userToTransferTo, $values) {
            foreach ($providers as $provider) {
                $provider->transfer($subscription, $userToTransferTo, $values);
            }
        });
    }
}
