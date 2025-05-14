<?php

namespace Crm\SubscriptionsModule\DataProviders;

use Crm\ApplicationModule\Models\DataProvider\DataProviderException;
use Crm\ApplicationModule\Models\NowTrait;
use Crm\SubscriptionsModule\Repositories\SubscriptionMetaRepository;
use Crm\SubscriptionsModule\Repositories\SubscriptionsRepository;
use Crm\UsersModule\Repositories\AddressesRepository;
use Nette\Database\Table\ActiveRow;
use Nette\Utils\ArrayHash;
use Tracy\Debugger;

class SubscriptionTransferDataProvider implements SubscriptionTransferDataProviderInterface
{
    use NowTrait;

    public function __construct(
        private readonly SubscriptionsRepository $subscriptionsRepository,
        private readonly AddressesRepository $addressesRepository,
        private readonly SubscriptionMetaRepository $subscriptionMetaRepository,
    ) {
    }

    /**
     * @inheritDoc
     */
    public function provide(array $params): void
    {
    }

    public function transfer(ActiveRow $subscription, ActiveRow $userToTransferTo, ArrayHash $formData): void
    {
        if (!$this->isTransferable($subscription)) {
            // this should never happen, as a back-end should check transferability before calling providers
            throw new DataProviderException('Subscription is not transferable');
        }

        $this->unlinkPreviousSubscription($subscription);
        $this->transferSubscription($subscription, $userToTransferTo);
        $this->linkPreviousSubscription($subscription, $userToTransferTo);

        $subscription = $this->subscriptionsRepository->find($subscription->id);

        $this->unlinkUnownedAddress($subscription, $userToTransferTo);
    }

    public function isTransferable(ActiveRow $subscription): bool
    {
        return $subscription->end_time > $this->getNow();
    }

    private function unlinkPreviousSubscription(ActiveRow $subscription): void
    {
        $this->subscriptionsRepository->getTable()
            ->where([
                'user_id' => $subscription->user_id,
                'next_subscription_id' => $subscription->id,
            ])
            ->update(['next_subscription_id' => null]);
    }

    private function transferSubscription(ActiveRow $subscription, ActiveRow $userToTransferTo): void
    {
        $this->subscriptionMetaRepository->add(
            $subscription,
            SubscriptionTransferDataProviderInterface::META_KEY_TRANSFERRED_FROM_USER,
            $subscription->user_id,
        );

        $this->subscriptionsRepository->update(
            $subscription,
            ['user_id' => $userToTransferTo->id],
        );
    }

    private function linkPreviousSubscription(ActiveRow $subscription, ActiveRow $userToTransferTo): void
    {
        $this->subscriptionsRepository->getTable()
            ->where([
                'user_id' => $userToTransferTo->id,
                'end_time' => $subscription->start_time,
            ])
            ->where('next_subscription_id IS NULL')
            ->update(['next_subscription_id' => $subscription->id]);
    }

    /**
     * Unlink address from subscription if it is not owned by the user to transfer to.
     * This needs to be done as the last step in the transfer process.
     */
    private function unlinkUnownedAddress(ActiveRow $subscription, ActiveRow $userToTransferTo): void
    {
        if ($subscription->address_id === null) {
            return;
        }

        $address = $subscription->address;
        if ($address->user_id === $userToTransferTo->id) {
            return;
        }

        $this->subscriptionsRepository->update(
            $subscription,
            ['address_id' => null],
        );

        Debugger::log(sprintf(
            'Unhandled address within subscription transfer. SubscriptionId:%d AddressId:%d AddressType:%s',
            $subscription->id,
            $address->id,
            $address->type,
        ), Debugger::WARNING);
    }
}
