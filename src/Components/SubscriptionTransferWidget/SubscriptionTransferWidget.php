<?php

namespace Crm\SubscriptionsModule\Components\SubscriptionTransferWidget;

use Crm\ApplicationModule\Models\DataProvider\DataProviderManager;
use Crm\ApplicationModule\Models\Widget\BaseLazyWidget;
use Crm\ApplicationModule\Models\Widget\LazyWidgetManager;
use Crm\SubscriptionsModule\DataProviders\SubscriptionTransferDataProviderInterface;
use Nette\Database\Table\ActiveRow;

class SubscriptionTransferWidget extends BaseLazyWidget
{
    public function __construct(
        LazyWidgetManager $widgetManager,
        private readonly DataProviderManager $dataProviderManager,
    ) {
        parent::__construct($widgetManager);
    }

    public function identifier()
    {
        return 'subscriptiontransferwidget';
    }

    public function render(ActiveRow $subscription)
    {
        if (!$this->isTransferable($subscription)) {
            return;
        }

        $this->template->subscription = $subscription;
        $this->template->setFile(__DIR__ . DIRECTORY_SEPARATOR . 'subscription_transfer_widget.latte');
        $this->template->render();
    }

    private function isTransferable(ActiveRow $subscription): bool
    {
        /** @var SubscriptionTransferDataProviderInterface[] $providers */
        $providers = $this->dataProviderManager->getProviders(
            'subscriptions.dataprovider.transfer',
            SubscriptionTransferDataProviderInterface::class,
        );

        foreach ($providers as $provider) {
            if (!$provider->isTransferable($subscription)) {
                return false;
            }
        }

        return true;
    }
}
