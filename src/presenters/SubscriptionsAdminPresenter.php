<?php

namespace Crm\SubscriptionsModule\Presenters;

use Crm\AdminModule\Presenters\AdminPresenter;
use Crm\SubscriptionsModule\Forms\SubscriptionFormFactory;
use Crm\SubscriptionsModule\Repository\SubscriptionsRepository;
use Crm\UsersModule\Repository\UsersRepository;
use Nette\Application\BadRequestException;

class SubscriptionsAdminPresenter extends AdminPresenter
{
    /** @var SubscriptionsRepository @inject */
    public $subscriptionsRepository;

    /** @var UsersRepository @inject */
    public $usersRepository;

    /** @var SubscriptionFormFactory @inject */
    public $factory;

    public function renderEdit($id, $userId)
    {
        $subscription = $this->subscriptionsRepository->find($id);
        if (!$subscription) {
            throw new BadRequestException();
        }
        $this->template->subscription = $subscription;
        $this->template->user = $subscription->user;
    }

    public function renderNew($userId)
    {
        $user = $this->usersRepository->find($userId);
        if (!$user) {
            throw new BadRequestException();
        }
        $this->template->user = $user;
    }

    public function createComponentSubscriptionForm()
    {
        $id = null;
        $user = null;

        if (isset($this->params['id'])) {
            $id = $this->params['id'];
            $subscription = $this->subscriptionsRepository->find($id);
            if (!$subscription) {
                throw new BadRequestException('Subscription does not exist: ' . $id);
            }
            $user = $subscription->user;
        }

        if (!$user && isset($this->params['userId'])) {
            $user = $this->usersRepository->find($this->params['userId']);
        }
        if (!$user) {
            throw new BadRequestException();
        }

        $form = $this->factory->create($user, $id);

        $presenter = $this;
        $this->factory->onSave = function ($subscription) use ($presenter) {
            $presenter->flashMessage($this->translator->translate('subscriptions.admin.subscriptions.messages.subscription_created'));
            $presenter->redirect(':Users:UsersAdmin:Show', $subscription->user->id);
        };
        $this->factory->onUpdate = function ($subscription) use ($presenter) {
            $presenter->flashMessage($this->translator->translate('subscriptions.admin.subscriptions.messages.subscription_updated'));
            $presenter->redirect(':Users:UsersAdmin:Show', $subscription->user->id);
        };
        return $form;
    }
}
