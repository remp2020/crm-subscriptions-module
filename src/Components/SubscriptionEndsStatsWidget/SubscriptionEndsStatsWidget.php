<?php

namespace Crm\SubscriptionsModule\Components\SubscriptionEndsStatsWidget;

use Crm\ApplicationModule\Models\Widget\BaseLazyWidget;
use Crm\SubscriptionsModule\Components\SubscriptionEndsStats\SubscriptionEndsStatsFactoryInterface;
use Nette\Utils\DateTime;

class SubscriptionEndsStatsWidget extends BaseLazyWidget
{
    private string $templateName = 'subscription_ends_stats_widget.latte';

    public function render(): void
    {
        $this->template->setFile(__DIR__ . DIRECTORY_SEPARATOR . $this->templateName);
        $this->template->render();
    }

    public function createComponentStats(SubscriptionEndsStatsFactoryInterface $factory)
    {
        $control = $factory->create();
        $control->setStartTime(DateTime::from($this->getPresenter()->params['dateFrom']));
        $control->setEndTime(DateTime::from($this->getPresenter()->params['dateTo']));
        $control->setWithoutNext(true);
        $control->setWithoutRecurrent(true);
        return $control;
    }
}
