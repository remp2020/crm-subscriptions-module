<?php

namespace Crm\SubscriptionsModule\Components\ActualSubscriptionsStatWidget;

use Crm\ApplicationModule\Widget\BaseLazyWidget;
use Crm\ApplicationModule\Widget\LazyWidgetManager;
use Crm\SubscriptionsModule\Repositories\SubscriptionsRepository;

/**
 * This simple widget fetches actual subscribers count and renders
 * single count stat. Used in dashboard.
 *
 * @package Crm\SubscriptionsModule\Components
 */
class ActualSubscriptionsStatWidget extends BaseLazyWidget
{
    private $templateName = 'actual_subscriptions_stat_widget.latte';

    /** @var SubscriptionsRepository */
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
        return 'actualsubscriptionsstatwidget';
    }

    public function render()
    {
        $this->template->actualSubscriptions = $this->subscriptionsRepository->actualSubscriptions()->count('*');
        $this->template->setFile(__DIR__ . '/' . $this->templateName);
        $this->template->render();
    }
}
