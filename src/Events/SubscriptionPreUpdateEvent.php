<?php

namespace Crm\SubscriptionsModule\Events;

use League\Event\AbstractEvent;
use Nette\Application\UI\Form;
use Nette\Database\Table\ActiveRow;

class SubscriptionPreUpdateEvent extends AbstractEvent implements SubscriptionEventInterface
{
    private $subscription;

    private $values;

    private $form;

    public function __construct(ActiveRow $subscription, Form &$form, $values)
    {
        $this->subscription = $subscription;
        $this->values = $values;
        $this->form = $form;
    }

    public function getSubscription()
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
