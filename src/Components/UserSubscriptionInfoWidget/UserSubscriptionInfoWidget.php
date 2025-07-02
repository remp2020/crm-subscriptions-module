<?php

namespace Crm\SubscriptionsModule\Components\UserSubscriptionInfoWidget;

use Crm\ApplicationModule\Models\NowTrait;
use Crm\ApplicationModule\Models\Widget\BaseLazyWidget;

/**
 * This widget displays info about user's actual subscriptions or
 * last expired subscription in user listing.
 *
 * @package Crm\SubscriptionsModule\Components
 */
class UserSubscriptionInfoWidget extends BaseLazyWidget
{
    use NowTrait;

    private $templateName = 'user_subscription_info_widget.latte';

    public function identifier()
    {
        return 'usersubscriptioninfowidget';
    }

    public function render($user)
    {
        $now = $this->getNow();
        $subscriptions = $user->related('subscriptions')
            ->select('subscriptions.*, TRUE AS actual')
            ->where('start_time < ?', $now)
            ->where('end_time > ?', $now)
            ->fetchAll();

        if (empty($subscriptions)) {
            $subscriptions = $user->related('subscriptions')
                ->select('subscriptions.*, FALSE AS actual')
                ->where('end_time < ?', $now)
                ->order('end_time DESC')
                ->limit(1)
                ->fetchAll();
        }

        $this->template->subscriptions = $subscriptions;
        $this->template->setFile(__DIR__ . '/' . $this->templateName);
        $this->template->render();
    }
}
