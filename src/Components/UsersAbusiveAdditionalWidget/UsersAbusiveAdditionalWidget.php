<?php

namespace Crm\SubscriptionsModule\Components\UsersAbusiveAdditionalWidget;

use Crm\ApplicationModule\Widget\BaseLazyWidget;
use Crm\ApplicationModule\Widget\LazyWidgetManager;
use Crm\SubscriptionsModule\Repositories\SubscriptionsRepository;
use Nette\Database\Table\ActiveRow;

class UsersAbusiveAdditionalWidget extends BaseLazyWidget
{
    private $templateName = 'users_abusive_additional_widget.latte';

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
        return 'subscriptionusersabusiveadditionalwidget';
    }

    public function render(ActiveRow $userRow)
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
