<?php

namespace Crm\SubscriptionsModule\Components;

use Crm\ApplicationModule\Widget\BaseLazyWidget;

/**
 * This widgets renders subscription edit link for specific payment.
 *
 * @package Crm\SubscriptionsModule\Components
 */
class SubscriptionButton extends BaseLazyWidget
{
    private $templateName = 'subscription_button.latte';

    public function header()
    {
        return 'Subscription';
    }

    public function identifier()
    {
        return 'usersubscription';
    }

    public function render($payment)
    {
        if (!$payment->subscription_id) {
            return;
        }

        $this->template->payment = $payment;
        $this->template->setFile(__DIR__ . '/' . $this->templateName);
        $this->template->render();
    }
}
