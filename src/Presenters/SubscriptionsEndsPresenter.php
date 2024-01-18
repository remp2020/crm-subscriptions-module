<?php

namespace Crm\SubscriptionsModule\Presenters;

use Crm\AdminModule\Presenters\AdminPresenter;
use Crm\SubscriptionsModule\Components\SubscriptionEndsStats\SubscriptionEndsStatsFactoryInterface;
use Crm\SubscriptionsModule\Repositories\ContentAccessRepository;
use Crm\SubscriptionsModule\Repositories\SubscriptionTypesRepository;
use Crm\SubscriptionsModule\Repositories\SubscriptionsRepository;
use Nette\Application\Attributes\Persistent;
use Nette\Application\UI\Form;
use Nette\DI\Attributes\Inject;
use Nette\Utils\DateTime;
use Tomaj\Form\Renderer\BootstrapRenderer;

class SubscriptionsEndsPresenter extends AdminPresenter
{
    #[Inject]
    public SubscriptionsRepository $subscriptionsRepository;

    #[Inject]
    public SubscriptionTypesRepository $subscriptionTypesRepository;

    #[Inject]
    public ContentAccessRepository $contentAccessRepository;

    #[Persistent]
    public $startTime;

    #[Persistent]
    public $endTime;

    #[Persistent]
    public $withoutNext;

    #[Persistent]
    public $withoutRecurrent;

    #[Persistent]
    public $freeSubscriptions;

    #[Persistent]
    public array $contentAccessTypes = [];

    public function startup()
    {
        parent::startup();

        $this->startTime = $this->startTime ?? DateTime::from(strtotime('-1 week'))->format('Y-m-d 00:00:00');
        $this->endTime = $this->endTime ?? (new DateTime())->format('Y-m-d 23:59:59');
    }

    /**
     * @admin-access-level read
     */
    public function renderDefault()
    {
        $subscriptions = $this->subscriptionsRepository->subscriptionsEndBetween(
            $this->startDateTime(),
            $this->endDateTime(),
            $this->withoutNext ? false : null
        );
        $subscriptions1 = $this->subscriptionsRepository->subscriptionsEndBetween(
            $this->startDateTime(),
            $this->endDateTime(),
            false
        );

        if (!$this->freeSubscriptions) {
            $subscriptions
                ->where('subscription_type.price > ?', 0)
                ->where('subscriptions.type NOT IN (?)', ['free']);
        }
        if ($this->withoutRecurrent) {
            $subscriptions->where('subscriptions.id NOT', $subscriptions1->where([
                ':payments:recurrent_payments.status' => null,
                ':payments:recurrent_payments.retries > ?' => 0,
                ':payments:recurrent_payments.state = ?' => 'active'
            ])->fetchPairs(null, 'id'));
        }

        if ($this->contentAccessTypes) {
            $subscriptions->where('subscription_type:subscription_type_content_access.content_access.name IN (?)', $this->contentAccessTypes);
        }

        $data = $subscriptions->fetchAll();
        $this->template->subscriptions = $data;
    }

    protected function createComponentSubscriptionEndsStats(SubscriptionEndsStatsFactoryInterface $factory)
    {
        $control = $factory->create();
        $control->setStartTime($this->startDateTime());
        $control->setEndTime($this->endDateTime());
        $control->setWithoutNext($this->withoutNext);
        $control->setWithoutRecurrent($this->withoutRecurrent);
        $control->setFreeSubscriptions($this->freeSubscriptions);
        return $control;
    }

    public function createComponentAdminFilterForm()
    {
        $form = new Form();
        $form->setTranslator($this->translator);
        $form->setRenderer(new BootstrapRenderer());
        $form->addText('startTime', 'subscriptions.data.subscriptions.fields.start_time')
            ->setHtmlAttribute('autofocus')
            ->setHtmlAttribute('class', 'flatpickr');
        $form->addText('endTime', 'subscriptions.data.subscriptions.fields.end_time')
            ->setHtmlAttribute('class', 'flatpickr');
        $form->addCheckbox('withoutNext', 'subscriptions.admin.subscriptions_ends.default.without_next');
        $form->addCheckbox('withoutRecurrent', 'subscriptions.admin.subscriptions_ends.default.without_recurrent');
        $form->addCheckbox('freeSubscriptions', 'subscriptions.admin.subscriptions_ends.default.free_subscriptions');

        $form->addMultiSelect('contentAccessTypes', 'subscriptions.admin.subscription_end_stats.content_access_types', $this->contentAccessRepository->all()->fetchPairs('name', 'name'))
            ->getControlPrototype()->addAttributes(['class' => 'select2']);

        $form->addSubmit('send', 'system.filter')
            ->getControlPrototype()
            ->setName('button')
            ->setHtml('<i class="fa fa-filter"></i> ' . $this->translator->translate('system.filter'));

        $presenter = $this;
        $form->addSubmit('cancel', 'system.cancel_filter')->onClick[] = function () use ($presenter) {
            $presenter->redirect('default', ['text' => '']);
        };
        $form->onSuccess[] = [$this, 'adminFilterSubmitted'];
        $form->setDefaults((array) $this->params);
        return $form;
    }

    private function startDateTime(): DateTime
    {
        return DateTime::from($this->startTime);
    }

    private function endDateTime(): DateTime
    {
        return DateTime::from($this->endTime);
    }
}
