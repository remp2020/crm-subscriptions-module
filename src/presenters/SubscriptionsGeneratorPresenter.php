<?php

namespace Crm\SubscriptionsModule\Presenters;

use Crm\AdminModule\Presenters\AdminPresenter;
use Crm\SubscriptionsModule\Forms\SubscriptionsGeneratorFormFactory;

class SubscriptionsGeneratorPresenter extends AdminPresenter
{
    /** @var SubscriptionsGeneratorFormFactory @inject */
    public $subscriptionsGeneratorFormFactory;

    /**
     * @admin-access-level read
     */
    public function renderDefault()
    {
    }

    public function createComponentSubscriptionsGeneratorForm()
    {
        $this->subscriptionsGeneratorFormFactory->onSubmit = function ($messages) {
            foreach ($messages as $message) {
                $this->flashMessage($message['text'], $message['type'] ?? 'info');
            }
        };

        $form = $this->subscriptionsGeneratorFormFactory->create();

        return $form;
    }
}
