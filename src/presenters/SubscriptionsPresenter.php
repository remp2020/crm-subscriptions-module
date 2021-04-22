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

        $this->template->showTrackingCode = false;
        $session = $this->getSession('success_login');
        if (isset($session->success) && $session->success == 'success') {
            unset($session->success);
            $this->template->showTrackingCode = true;
        }

        $this->template->userId = $this->getUser()->getId();
        $this->template->subscriptions = $this->subscriptionsRepository->userSubscriptions($this->getUser()->getId());
    }

    public function renderNew($funnel = null)
    {
        $homepage = $this->applicationConfig->get('homepage_url');
        if ($homepage) {
            $this->redirectUrl($homepage);
        }

        if ($funnel === null) {
            $funnel = $this->applicationConfig->get('default_sales_funnel_url_key');
        }
        $this->template->funnel = $funnel;

        $showHeader = false;
        $this->template->showHeader = $showHeader;
    }
}
