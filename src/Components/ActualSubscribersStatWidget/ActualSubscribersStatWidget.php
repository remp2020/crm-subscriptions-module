<?php

namespace Crm\SubscriptionsModule\Components;

use Crm\ApplicationModule\Widget\BaseWidget;
use Crm\ApplicationModule\Widget\WidgetManager;
use Crm\SegmentModule\Repository\SegmentsRepository;
use Crm\SubscriptionsModule\Repository\SubscriptionsRepository;

/**
 * This simple widget fetches actual subscribers count and renders
 * single count stat. Used in dashboard.
 *
 * @package Crm\SubscriptionsModule\Components
 */
class ActualSubscribersStatWidget extends BaseWidget
{
    private $templateName = 'actual_subscribers_stat_widget.latte';

    private $subscriptionsRepository;

    private $segmentsRepository;

    public function __construct(
        WidgetManager $widgetManager,
        SubscriptionsRepository $subscriptionsRepository,
        SegmentsRepository $segmentsRepository
    ) {
        parent::__construct($widgetManager);
        $this->subscriptionsRepository = $subscriptionsRepository;
        $this->segmentsRepository = $segmentsRepository;
    }

    public function identifier()
    {
        return 'actualsubscribersstatwidget';
    }

    public function render()
    {
        if ($this->segmentsRepository->exists('users_with_active_subscriptions')) {
            $this->template->totalSubscribersLink = $this->presenter->link(
                ':Segment:StoredSegments:show',
                $this->segmentsRepository->findByCode('users_with_active_subscriptions')->id
            );
        }
        $this->template->totalSubscribers = $this->subscriptionsRepository->currentSubscribersCount(true);
        $this->template->setFile(__DIR__ . DIRECTORY_SEPARATOR . $this->templateName);
        $this->template->render();
    }
}
