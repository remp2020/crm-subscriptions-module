<?php

namespace Crm\SubscriptionsModule\Components\UserSubscriptionInfoWidget;

use Crm\ApplicationModule\Models\Widget\BaseLazyWidget;

/**
 * This widget displays info about user's actual subscriptions or
 * last expired subscription in user listing.
 *
 * @package Crm\SubscriptionsModule\Components
 */
class UserSubscriptionInfoWidget extends BaseLazyWidget
{
    private $templateName = 'user_subscription_info_widget.latte';

    public function identifier()
    {
        return 'usersubscriptioninfowidget';
    }

    public function render($user)
    {
        $subscriptions = $user->related('subscriptions')->select('subscriptions.*, TRUE AS actual')->where('start_time < NOW()')->where('end_time > NOW()')->fetchAll();
        if (empty($subscriptions)) {
            $subscriptions = $user->related('subscriptions')->select('subscriptions.*, FALSE AS actual')->where('end_time < NOW()')->order('end_time DESC')->limit(1)->fetchAll();
        }

        $this->template->subscriptions = $subscriptions;
        $this->template->setFile(__DIR__ . '/' . $this->templateName);
        $this->template->render();
    }
}
