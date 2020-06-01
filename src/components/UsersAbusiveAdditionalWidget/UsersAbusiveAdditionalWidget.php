<?php

namespace Crm\SubscriptionsModule\Components;

use Crm\ApplicationModule\Widget\BaseWidget;
use Crm\ApplicationModule\Widget\WidgetManager;
use Crm\SubscriptionsModule\Repository\SubscriptionsRepository;
use Nette\Database\Table\IRow;

class UsersAbusiveAdditionalWidget extends BaseWidget
{
    private $templateName = 'users_abusive_additional_widget.latte';

    private $subscriptionsRepository;

    public function __construct(
        WidgetManager $widgetManager,
        SubscriptionsRepository $subscriptionsRepository
    ) {
        parent::__construct($widgetManager);

        $this->subscriptionsRepository = $subscriptionsRepository;
    }

    public function identifier()
    {
        return 'subscriptionusersabusiveadditionalwidget';
    }

    public function render(IRow $userRow)
    {
        $actualSubscriptionsSelection = $this->subscriptionsRepository->actualUserSubscriptions($userRow->id);
        $actualSubscriptionRow = current($actualSubscriptionsSelection->fetchAll());

        if ($actualSubscriptionRow) {
            $this->template->actualSubscriptionRow = $actualSubscriptionRow;
            $this->template->setFile(__DIR__ . '/' . $this->templateName);
            $this->template->render();
        }
    }
}
