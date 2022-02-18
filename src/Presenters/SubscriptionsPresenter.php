<?php

namespace Crm\SubscriptionsModule\Presenters;

use Crm\ApplicationModule\Presenters\FrontendPresenter;
use Crm\SubscriptionsModule\Repository\SubscriptionsRepository;

class SubscriptionsPresenter extends FrontendPresenter
{
    /** @var SubscriptionsRepository */
    public $subscriptionsRepository;

    public function __construct(SubscriptionsRepository $subscriptionsRepository)
    {
        parent::__construct();
        $this->subscriptionsRepository = $subscriptionsRepository;
    }

    public function renderMy()
    {
        $this->onlyLoggedIn();

        $this->template->userId = $this->getUser()->getId();
        $this->template->subscriptions = $this->subscriptionsRepository
            ->userSubscriptions($this->getUser()->getId())
            ->where('subscriptions.end_time > subscriptions.start_time');
    }

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
