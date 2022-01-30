<?php

namespace Crm\SubscriptionsModule\Components;

use Crm\ApplicationModule\Widget\BaseWidget;
use Crm\ApplicationModule\Widget\WidgetManager;
use Crm\SubscriptionsModule\Repository\SubscriptionsRepository;

/**
 * This widget fetches users actual subscription and renders
 * simple bootstrap panel showing start, end date and button to create new subscription.
 *
 * @package Crm\SubscriptionsModule\Components
 */
class ActualUserSubscriptions extends BaseWidget
{
    private $templateName = 'actual_user_subscriptions.latte';

    /** @var SubscriptionsRepository */
    private $subscriptionsRepository;

    public function __construct(
        WidgetManager $widgetManager,
        SubscriptionsRepository $subscriptionsRepository
    ) {
        parent::__construct($widgetManager);
        $this->subscriptionsRepository = $subscriptionsRepository;
    }

    public function header($id = '')
    {
        return 'Actual subscription';
    }

    public function identifier()
    {
        return 'useractualsubscriptions';
    }

    public function render($id)
    {
        $this->template->totalSubscriptions = $this->subscriptionsRepository->userSubscriptions($id)->count('*');
        $this->template->actualSubscription = $this->subscriptionsRepository->actualUserSubscription($id);
        $this->template->userId = $id;

        $this->template->setFile(__DIR__ . '/' . $this->templateName);
        $this->template->render();
    }
}
