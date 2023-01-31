<?php

namespace Crm\SubscriptionsModule\Components;

use Crm\ApplicationModule\Widget\BaseLazyWidget;
use Crm\ApplicationModule\Widget\LazyWidgetManager;
use Crm\SubscriptionsModule\Repository\SubscriptionsRepository;
use Crm\SubscriptionsModule\Subscription\StopSubscriptionHandler;
use DateTime;
use Nette\Database\Table\ActiveRow;
use Nette\Localization\Translator;

class StopSubscriptionWidget extends BaseLazyWidget
{
    private $templateName = 'stop_subscription_widget.latte';

    private $subscriptionsRepository;

    private $translator;

    private $stopSubscriptionHandler;

    public function __construct(
        LazyWidgetManager $lazyWidgetManager,
        SubscriptionsRepository $subscriptionsRepository,
        StopSubscriptionHandler $stopSubscriptionHandler,
        Translator $translator
    ) {
        parent::__construct($lazyWidgetManager);
        $this->subscriptionsRepository = $subscriptionsRepository;
        $this->translator = $translator;
        $this->stopSubscriptionHandler = $stopSubscriptionHandler;
    }

    public function identifier()
    {
        return 'stopsubscriptionwidget';
    }

    public function render(ActiveRow $subscription)
    {
        if (!$this->isSubscriptionStoppable($subscription)) {
            return;
        }

        $this->template->subscription = $subscription;
        $this->template->setFile(__DIR__ . DIRECTORY_SEPARATOR . $this->templateName);
        $this->template->render();
    }

    private function isSubscriptionStoppable(ActiveRow $subscription): bool
    {
        // already stopped
        if ($subscription->start_time == $subscription->end_time) {
            return false;
        }

        if ($subscription->end_time > new DateTime()) {
            return true;
        }

        return false;
    }

    public function handleStopSubscription(int $subscriptionId)
    {
        $subscription = $this->subscriptionsRepository->find($subscriptionId);
        if (!$subscription) {
            $this->getPresenter()->flashMessage($this->translator->translate('subscriptions.admin.stop_subscription_widget.no_subscription', ['id' => $subscriptionId]), 'error');
            return;
        }

        $this->stopSubscriptionHandler->stopSubscription(
            subscription: $subscription,
            expiredByAdmin: true,
        );

        $this->getPresenter()->flashMessage($this->translator->translate('subscriptions.admin.stop_subscription_widget.success', ['id' => $subscriptionId]));
        $this->redirect('this');
    }
}
