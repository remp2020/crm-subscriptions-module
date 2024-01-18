<?php

namespace Crm\SubscriptionsModule\Components\MonthToDateSubscriptionsStatWidget;

use Crm\ApplicationModule\Widget\BaseLazyWidget;
use Crm\ApplicationModule\Widget\LazyWidgetManager;
use Crm\SubscriptionsModule\Repositories\SubscriptionsRepository;
use Nette\Utils\DateTime;

/**
 * This widget fetches subscriptions created from start of month to date
 * and last months same value.
 * Renders simple line with both lines.
 *
 * @package Crm\SubscriptionsModule\Components
 */
class MonthToDateSubscriptionsStatWidget extends BaseLazyWidget
{
    private $templateName = 'month_to_date_subscriptions_stat_widget.latte';

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
        return 'monthtodatesubscriptionsstatwidget';
    }

    public function render()
    {
        $this->template->thisMonthSubscriptions = $this->subscriptionsRepository->subscriptionsCreatedBetween(
            DateTime::from(date('Y-m')),
            new DateTime()
        )->count('*');
        $this->template->lastMonthDaySubscriptions = $this->subscriptionsRepository->subscriptionsCreatedBetween(
            DateTime::from('first day of last month 00:00'),
            DateTime::from('-1 month')
        )->count('*');
        $this->template->setFile(__DIR__ . DIRECTORY_SEPARATOR . $this->templateName);
        $this->template->render();
    }
}
