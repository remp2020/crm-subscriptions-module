<?php

namespace Crm\SubscriptionsModule\Presenters;

use Crm\ApplicationModule\Presenters\FrontendPresenter;
use Crm\SubscriptionsModule\Repositories\SubscriptionsRepository;

class SubscriptionsPresenter extends FrontendPresenter
{
    public SubscriptionsRepository $subscriptionsRepository;

    public function __construct(SubscriptionsRepository $subscriptionsRepository)
    {
        parent::__construct();
        $this->subscriptionsRepository = $subscriptionsRepository;
    }

    public function renderMy(): void
    {
        $this->onlyLoggedIn();

        $userId = $this->getUser()->getId();

        $this->template->userId = $userId;
        $this->template->subscriptions = $this->subscriptionsRepository
            ->userSubscriptions($userId)
            ->where('subscriptions.end_time > subscriptions.start_time')
            ->fetchAll();
        $this->template->noSubscriptionsRoute = $this->applicationConfig->get('default_route');
    }

    /**
     * @deprecated 4.0 No longer supported way of rendering sales funnels
     * @see crm-salesfunnel-module/README.md#iframe-deprecation-in-sales-funnels
     */
    public function renderNew($funnel = null)
    {
        if ($funnel === null) {
            $funnel = $this->applicationConfig->get('default_sales_funnel_url_key');
        }
        $this->template->funnel = $funnel;

        $showHeader = false;
        $this->template->showHeader = $showHeader;
    }
}
