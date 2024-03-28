<?php
declare(strict_types=1);

namespace Crm\SubscriptionsModule\Components\UserSubscriptionAddressWidget;

use Crm\ApplicationModule\Models\Widget\BaseLazyWidget;
use Crm\ApplicationModule\Models\Widget\LazyWidgetManager;
use Nette\Database\Table\ActiveRow;

class UserSubscriptionAddressWidget extends BaseLazyWidget
{
    private $templateName = 'user_subscription_address_widget.latte';

    public function __construct(
        LazyWidgetManager $widgetManager
    ) {
        parent::__construct($widgetManager);
    }

    public function identifier(): string
    {
        return 'user_subscription_address_widget';
    }

    public function render(ActiveRow $subscription): void
    {
        if (!$subscription->address_id) {
            return;
        }

        $this->template->subscription = $subscription;
        $this->template->setFile(__DIR__ . DIRECTORY_SEPARATOR . $this->templateName);
        $this->template->render();
    }
}
