<?php

namespace Crm\SubscriptionsModule\Components\UserSubscriptionsListing;

use Crm\ApplicationModule\Models\Widget\BaseLazyWidget;
use Crm\ApplicationModule\Models\Widget\LazyWidgetManager;
use Crm\SubscriptionsModule\Repositories\SubscriptionsRepository;
use Nette\Localization\Translator;

/**
 * This component fetches specific users subscriptions and render
 * data table. Used in user detail.
 *
 * @package Crm\SubscriptionsModule\Components
 */
class UserSubscriptionsListing extends BaseLazyWidget
{
    private $templateName = 'user_subscriptions_listing.latte';

    private $subscriptionsRepository;

    private $translator;

    public function __construct(
        LazyWidgetManager $lazyWidgetManager,
        SubscriptionsRepository $subscriptionsRepository,
        Translator $translator
    ) {
        parent::__construct($lazyWidgetManager);
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
