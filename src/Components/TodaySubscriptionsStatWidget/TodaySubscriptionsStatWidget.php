<?php

namespace Crm\SubscriptionsModule\Components\TodaySubscriptionsStatWidget;

use Crm\ApplicationModule\Widget\BaseLazyWidget;
use Crm\ApplicationModule\Widget\LazyWidgetManager;
use Crm\SubscriptionsModule\Repositories\SubscriptionsRepository;
use Nette\Utils\DateTime;

/**
 * This widget fetches subscriptions created today and renders
 * simple single stat widget.
 *
 * @package Crm\SubscriptionsModule\Components
 */
class TodaySubscriptionsStatWidget extends BaseLazyWidget
{
    private $templateName = 'today_subscriptions_stat_widget.latte';

    private $subscriptionsRepository;

    public function __construct(
        LazyWidgetManager $lazyWidgetManager,
        SubscriptionsRepository $subscriptionsRepository
    ) {
        parent::__construct($lazyWidgetManager);
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
