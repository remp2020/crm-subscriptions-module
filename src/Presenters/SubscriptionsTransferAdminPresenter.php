<?php

namespace Crm\SubscriptionsModule\Presenters;

use Crm\AdminModule\Presenters\AdminPresenter;
use Crm\PaymentsModule\Repositories\PaymentsRepository;
use Crm\SubscriptionsModule\Forms\SubscriptionTransferConfirmationFormFactory;
use Crm\SubscriptionsModule\Forms\SubscriptionTransferUserSelectFormFactory;
use Crm\SubscriptionsModule\Models\SubscriptionTransfer\UserSearch;
use Crm\SubscriptionsModule\Repositories\SubscriptionsRepository;
use Crm\UsersModule\Repositories\UsersRepository;
use Nette\Application\BadRequestException;
use Nette\Application\UI\Form;
use Nette\Database\Table\ActiveRow;
use Nette\Http\IResponse;

class SubscriptionsTransferAdminPresenter extends AdminPresenter
{
    public function __construct(
        private readonly SubscriptionsRepository $subscriptionsRepository,
        private readonly PaymentsRepository $paymentsRepository,
        private readonly UsersRepository $usersRepository,
        private readonly UserSearch $userSearch,
        private readonly SubscriptionTransferUserSelectFormFactory $subscriptionTransferUserSelectFormFactory,
        private readonly SubscriptionTransferConfirmationFormFactory $subscriptionTransferConfirmationFormFactory,
    ) {
        parent::__construct();
    }

    /**
     * @admin-access-level write
     */
    public function renderSelectUser(int $id): void
    {
        $subscription = $this->subscriptionsRepository->find($id);
        if (!$subscription) {
            throw new BadRequestException(sprintf(
                'Subscription with id %d not found',
                $id
            ), httpCode: IResponse::S404_NotFound);
        }

        $this->template->subscription = $subscription;
    }

    /**
     * @admin-access-level write
     */
    public function renderSummary(int $id, int $userIdToTransferTo): void
    {
        $subscription = $this->subscriptionsRepository->find($id);
        if (!$subscription) {
            throw new BadRequestException(sprintf(
                'Subscription with id %d not found',
                $id
            ), httpCode: IResponse::S404_NotFound);
        }

        $userToTransferTo = $this->usersRepository->find($userIdToTransferTo);
        if (!$userToTransferTo) {
            throw new BadRequestException(sprintf(
                'User with id %d not found',
                $userIdToTransferTo
            ), httpCode: IResponse::S404_NotFound);
        }

        if ($userToTransferTo->id === $subscription->user_id) {
            throw new BadRequestException(sprintf(
                'Subscription %d cannot be transferred to the same user.',
                $subscription->id,
            ), httpCode: IResponse::S404_NotFound);
        }

        $payment = $this->paymentsRepository->subscriptionPayment($subscription);
        $actualSubscriptions = $this->subscriptionsRepository->actualUserSubscriptions($userToTransferTo->id);

        $this->template->subscription = $subscription;
        $this->template->payment = $payment;
        $this->template->userToTransferTo = $userToTransferTo;
        $this->template->actualSubscriptions = $actualSubscriptions;
    }

    /**
     * @admin-access-level write
     */
    public function handleSearchUser(int $id): void
    {
        $subscription = $this->subscriptionsRepository->find($id);
        if (!$subscription) {
            throw new BadRequestException(sprintf(
                'Subscription with id %d not found',
                $id
            ), httpCode: IResponse::S404_NotFound);
        }

        $searchTerm = trim($this->getParameter('term') ?? '');
        $foundUsers = $this->userSearch->search($searchTerm, $subscription->user_id);

        $transformedUsers = array_map(fn (ActiveRow $user) => [
            'id' => $user->id,
            'text' => sprintf(
                '#%d | %s',
                $user->id,
                $user->email,
            ),
        ], $foundUsers);

        $this->sendJson(['results' => $transformedUsers]);
    }

    public function createComponentUserSelect(): Form
    {
        $formFactory = $this->subscriptionTransferUserSelectFormFactory;
        $formFactory->onSelect = fn (int $userId) => $this->redirect('summary', [
            'id' => $this->getParameter('id'),
            'userIdToTransferTo' => $userId,
        ]);

        return $formFactory->create($this->link('searchUser!'));
    }

    public function createComponentTransferConfirmation(): Form
    {
        $subscriptionId = $this->getParameter('id');
        $userIdToTransferTo = $this->getParameter('userIdToTransferTo');

        $formFactory = $this->subscriptionTransferConfirmationFormFactory;
        $formFactory->onTransfer = function () use ($userIdToTransferTo) {
            $this->flashMessage($this->translator->translate('subscriptions.admin.subscriptions_transfer.summary.transfer_complete_message'));
            $this->redirect(':Users:UsersAdmin:show', $userIdToTransferTo);
        };

        return $formFactory->create($subscriptionId, $userIdToTransferTo);
    }
}
