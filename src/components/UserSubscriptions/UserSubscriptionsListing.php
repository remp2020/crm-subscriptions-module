<?php

namespace Crm\SubscriptionsModule\Components;

use Crm\ApplicationModule\Widget\BaseWidget;
use Crm\ApplicationModule\Widget\WidgetManager;
use Crm\SubscriptionsModule\Repository\SubscriptionsRepository;
use Nette\Localization\Translator;

/**
 * This component fetches specific users subscriptions and render
 * data table. Used in user detail.
 *
 * @package Crm\SubscriptionsModule\Components
 */
class UserSubscriptionsListing extends BaseWidget
{
    private $templateName = 'user_subscriptions_listing.latte';

    private $subscriptionsRepository;

    private $translator;

    public function __construct(
        WidgetManager $widgetManager,
        SubscriptionsRepository $subscriptionsRepository,
        Translator $translator
    ) {
        parent::__construct($widgetManager);
        $this->subscriptionsRepository = $subscriptionsRepository;
        $this->translator = $translator;
    }

    public function header($id = '')
    {
        $header = $this->translator->translate('subscriptions.admin.user_subscriptions.header');
        if ($id) {
            $header .= ' <small>(' . $this->totalCount($id) . ')</small>';
        }
        return $header;
    }

    public function identifier()
    {
        return 'usersubscriptions';
    }

    public function render($id)
    {
        $subscriptions = $this->subscriptionsRepository->userSubscriptions($id);

        $this->template->totalSubscriptions = $this->totalCount($id);
        $this->template->subscriptions = $subscriptions;
        $this->template->id = $id;
        $this->template->setFile(__DIR__ . '/' . $this->templateName);
        $this->template->render();
    }

    private $totalCount = null;

    private function totalCount($id)
    {
        if ($this->totalCount == null) {
            $this->totalCount = $this->subscriptionsRepository->userSubscriptions($id)->count('*');
        }
        return $this->totalCount;
    }
}
