<?php

namespace Crm\SubscriptionsModule\Events;

use Crm\ApplicationModule\UI\Form;
use League\Event\AbstractEvent;
use Nette\Database\Table\ActiveRow;

class SubscriptionPreUpdateEvent extends AbstractEvent implements SubscriptionEventInterface
{
    public function __construct(
        private ActiveRow $subscription,
        private Form &$form,
        private $values,
    ) {
    }

    public function getSubscription(): ActiveRow
    {
        return $this->subscription;
    }

    public function getValues()
    {
        return $this->values;
    }

    public function getForm(): Form
    {
        return $this->form;
    }
}
