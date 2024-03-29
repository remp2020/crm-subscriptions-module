<?php

namespace Crm\SubscriptionsModule\Components\SubscriptionEndsSuppressionWidget;

use Crm\ApplicationModule\Models\Database\ActiveRow;
use Crm\ApplicationModule\Models\Widget\BaseLazyWidget;
use Crm\ApplicationModule\Models\Widget\LazyWidgetManager;
use Crm\SubscriptionsModule\Models\Subscription\SubscriptionEndsSuppressionManager;
use Crm\SubscriptionsModule\Repositories\SubscriptionsRepository;
use Nette\Localization\Translator;

class SubscriptionEndsSuppressionWidget extends BaseLazyWidget
{
    private $templateName = 'subscription_ends_suppression_widget.latte';

    public function __construct(
        LazyWidgetManager $widgetManager,
        private SubscriptionsRepository $subscriptionsRepository,
        private SubscriptionEndsSuppressionManager $subscriptionEndsSuppressionManager,
        private Translator $translator,
    ) {
        parent::__construct($widgetManager);
    }

    public function identifier(): string
    {
        return 'subscriptionendssuppressionwidget';
    }

    public function render(ActiveRow $subscription): void
    {
        $this->template->hasSuppressedNotifications = $this->subscriptionEndsSuppressionManager->hasSuppressedNotifications($subscription);
        $this->template->subscription = $subscription;
        $this->template->setFile(__DIR__ . DIRECTORY_SEPARATOR . $this->templateName);
        $this->template->render();
    }

    public function handleToggleNotifications(int $subscriptionId, bool $value)
    {
        $subscription = $this->subscriptionsRepository->find($subscriptionId);
        if (!$subscription) {
            $this->getPresenter()->flashMessage($this->translator->translate('subscriptions.admin.subscription_ends_suppression_widget.no_subscription', ['id' => $subscriptionId]), 'error');
            return;
        }

        if ($value) {
            $this->subscriptionEndsSuppressionManager->suppressNotifications($subscription);
            $this->getPresenter()->flashMessage($this->translator->translate('subscriptions.admin.subscription_ends_suppression_widget.suppressed', ['id' => $subscriptionId]));
        } else {
            $this->subscriptionEndsSuppressionManager->resumeNotifications($subscription);
            $this->getPresenter()->flashMessage($this->translator->translate('subscriptions.admin.subscription_ends_suppression_widget.resumed', ['id' => $subscriptionId]));
        }

        $this->redirect('this');
    }
}
