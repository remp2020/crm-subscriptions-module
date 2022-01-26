<?php

namespace Crm\SubscriptionsModule\Components;

use Crm\ApplicationModule\Widget\BaseWidget;
use Crm\ApplicationModule\Widget\WidgetManager;
use Crm\SubscriptionsModule\Repository\SubscriptionsRepository;
use Nette\Utils\DateTime;

/**
 * This widget fetches subscriptions created today and renders
 * simple single stat widget.
 *
 * @package Crm\SubscriptionsModule\Components
 */
class TodaySubscriptionsStatWidget extends BaseWidget
{
    private $templateName = 'today_subscriptions_stat_widget.latte';

    private $subscriptionsRepository;

    public function __construct(
        WidgetManager $widgetManager,
        SubscriptionsRepository $subscriptionsRepository
    ) {
        parent::__construct($widgetManager);
        $this->subscriptionsRepository = $subscriptionsRepository;
    }

    public function identifier()
    {
        return 'todaysubscriptionsstatwidget';
    }

    public function render()
    {
        $this->template->todaySubscriptions = $this->subscriptionsRepository->subscriptionsCreatedBetween(
            DateTime::from('today 00:00'),
            new DateTime()
        )->count('*');
        $this->template->yesterdaySubscriptions = $this->subscriptionsRepository->subscriptionsCreatedBetween(
            DateTime::from('yesterday 00:00'),
            DateTime::from('today 00:00')
        )->count('*');
        $this->template->setFile(__DIR__ . DIRECTORY_SEPARATOR . $this->templateName);
        $this->template->render();
    }
}
