<?php

namespace Crm\SubscriptionsModule\Presenters;

use Crm\AdminModule\Components\DateFilterFormFactory;
use Crm\AdminModule\Presenters\AdminPresenter;
use Crm\ApplicationModule\Components\Graphs\GoogleBarGraphGroupControlFactoryInterface;
use Crm\ApplicationModule\Components\Graphs\GoogleLineGraphGroupControlFactoryInterface;
use Crm\ApplicationModule\DataProvider\DataProviderManager;
use Crm\ApplicationModule\Graphs\Criteria;
use Crm\ApplicationModule\Graphs\GraphDataItem;
use Crm\ApplicationModule\Models\Graphs\Scale\Measurements\RangeScaleFactory;
use Crm\SubscriptionsModule\Components\SubscriptionEndsStatsFactoryInterface;
use Crm\SubscriptionsModule\DataProvider\EndingSubscriptionsDataProviderInterface;
use Crm\SubscriptionsModule\DataProvider\SubscriptionAccessStatsDataProviderInterface;
use Crm\SubscriptionsModule\Measurements\ActivePayingSubscribersMeasurement;
use Crm\SubscriptionsModule\Measurements\ActiveSubscribersMeasurement;
use Crm\SubscriptionsModule\Measurements\ActiveSubscriptionsMeasurement;
use Crm\SubscriptionsModule\Repository\ContentAccessRepository;
use Nette\Database\Table\ActiveRow;
use Nette\Utils\DateTime;

class DashboardPresenter extends AdminPresenter
{
    /** @inject */
    public DataProviderManager $dataProviderManager;

    /** @inject */
    public ContentAccessRepository $contentAccessRepository;

    /** @persistent */
    public $dateFrom;

    /** @persistent */
    public $dateTo;

    public function startup()
    {
        parent::startup();

        if ($this->action == 'endings') {
            $this->dateFrom = $this->dateFrom ?? DateTime::from('now')->format('Y-m-d');
            $this->dateTo = $this->dateTo ?? DateTime::from('+6 months')->format('Y-m-d');
        } else {
            $this->dateFrom = $this->dateFrom ?? DateTime::from('-1 months')->format('Y-m-d');
            $this->dateTo = $this->dateTo ?? DateTime::from('today')->format('Y-m-d');
        }

        $this->template->dateFrom = $this->dateFrom;
        $this->template->dateTo = $this->dateTo;
    }

    /**
     * @admin-access-level read
     */
    public function renderDefault()
    {
    }

    /**
     * @admin-access-level read
     */
    public function renderEndings()
    {
    }

    public function createComponentDateFilterForm(DateFilterFormFactory $dateFilterFormFactory)
    {
        $form = $dateFilterFormFactory->create($this->dateFrom, $this->dateTo);
        $form->onSuccess[] = function ($form, $values) {
            $this->dateFrom = $values['date_from'];
            $this->dateTo = $values['date_to'];
            $this->redirect($this->action);
        };
        return $form;
    }

    public function createComponentGoogleSubscriptionsFlowGraph(GoogleLineGraphGroupControlFactoryInterface $factory)
    {
        $this->getSession()->close();
        $items = [];

        $graphDataItem = new GraphDataItem();
        $graphDataItem->setCriteria((new Criteria())
            ->setTableName('subscriptions')
            ->setRangeFields('start_time', 'end_time')
            ->setWhere('AND date = Date(start_time)')
            ->setValueField('count(*)')
            ->setStart($this->dateFrom)
            ->setEnd($this->dateTo));
        $graphDataItem->setName($this->translator->translate('dashboard.subscriptions.started'));
        $items[] = $graphDataItem;

        $graphDataItem = new GraphDataItem();
        $graphDataItem->setCriteria((new Criteria())
            ->setTableName('subscriptions')
            ->setRangeFields('start_time', 'end_time')
            ->setWhere('AND date = Date(end_time)')
            ->setValueField('-count(*)')
            ->setStart($this->dateFrom)
            ->setEnd($this->dateTo));
        $graphDataItem->setName($this->translator->translate('dashboard.subscriptions.ending.title'));
        $items[] = $graphDataItem;

        $control = $factory->create()
            ->setGraphTitle($this->translator->translate('dashboard.subscriptions.difference.title'))
            ->setGraphHelp($this->translator->translate('dashboard.subscriptions.difference.tooltip'))
            ->setFrom($this->dateFrom)
            ->setTo($this->dateTo);

        foreach ($items as $graphDataItem) {
            $control->addGraphDataItem($graphDataItem);
        }

        return $control;
    }

    public function createComponentGoogleSubscriptionsRecurrencyStatsGraph(GoogleLineGraphGroupControlFactoryInterface $factory)
    {
        $this->getSession()->close();
        $items = [];

        $graphDataItem = new GraphDataItem();
        $graphDataItem->setCriteria((new Criteria())
            ->setTableName('subscriptions')
            ->setRangeFields('start_time', 'end_time')
            ->setValueField('count(distinct subscriptions.user_id)')
            ->setWhere('AND is_recurrent = 1 AND is_paid = 1')
            ->setStart($this->dateFrom)
            ->setEnd($this->dateTo));
        $graphDataItem->setName($this->translator->translate('subscriptions.admin.dashboard.subscriptions_recurrency.recurrent_subscribers'));
        $items[] = $graphDataItem;

        $graphDataItem = new GraphDataItem();
        $graphDataItem->setCriteria((new Criteria())
            ->setTableName('subscriptions')
            ->setRangeFields('start_time', 'end_time')
            ->setValueField('count(distinct subscriptions.user_id)')
            ->setWhere('AND is_recurrent = 0 AND is_paid = 1')
            ->setStart($this->dateFrom)
            ->setEnd($this->dateTo));
        $graphDataItem->setName($this->translator->translate('subscriptions.admin.dashboard.subscriptions_recurrency.nonrecurrent_subscribers'));
        $items[] = $graphDataItem;

        $control = $factory->create()
            ->setGraphTitle($this->translator->translate('subscriptions.admin.dashboard.subscriptions_recurrency.title'))
            ->setGraphHelp($this->translator->translate('subscriptions.admin.dashboard.subscriptions_recurrency.tooltip'))
            ->setFrom($this->dateFrom)
            ->setTo($this->dateTo);

        foreach ($items as $graphDataItem) {
            $control->addGraphDataItem($graphDataItem);
        }
        return $control;
    }

    public function createComponentGoogleSubscriptionsLengthStatsGraph(GoogleLineGraphGroupControlFactoryInterface $factory)
    {
        $this->getSession()->close();
        $dayRanges = [
            ["from_days" => 1, "to_days" => 27],
            ["from_days" => 28, "to_days" => 55],
            ["from_days" => 56, "to_days" => 99],
            ["from_days" => 100, "to_days" => 364],
            ["from_days" => 365, "to_days" => 545]
        ];

        $items = [];

        foreach ($dayRanges as $range) {
            $graphDataItem = new GraphDataItem();
            $graphDataItem->setCriteria((new Criteria())
                ->setTableName('subscriptions')
                ->setRangeFields('start_time', 'end_time')
                ->setValueField('COUNT(DISTINCT subscriptions.user_id)')
                ->setWhere(' AND length >=' . $range["from_days"] . ' AND length <=' . $range["to_days"])
                ->setStart($this->dateFrom)
                ->setEnd($this->dateTo));
            
            $graphDataItem->setName(sprintf(
                "%s - %s %s",
                $range["from_days"],
                $range["to_days"],
                $this->translator->translate('subscriptions.admin.dashboard.subscriptions_length.days')
            ));
            $items[] = $graphDataItem;
        }

        // we add one more default group for all the rest

        $graphDataItem = new GraphDataItem();
        $graphDataItem->setCriteria((new Criteria())
            ->setTableName('subscriptions')
            ->setRangeFields('start_time', 'end_time')
            ->setValueField('count(distinct subscriptions.user_id)')
            ->setWhere(' AND length > ' . $dayRanges[array_key_last($dayRanges)]["to_days"])
            ->setStart($this->dateFrom)
            ->setEnd($this->dateTo));

        $graphDataItem->setName(sprintf(
            "%s %s",
            $dayRanges[array_key_last($dayRanges)]["to_days"] + 1,
            $this->translator->translate('subscriptions.admin.dashboard.subscriptions_length.and_more_days')
        ));
        $items[] = $graphDataItem;

        $control = $factory->create()
            ->setGraphTitle($this->translator->translate('subscriptions.admin.dashboard.subscriptions_length.title'))
            ->setGraphHelp($this->translator->translate('subscriptions.admin.dashboard.subscriptions_length.tooltip'))
            ->setFrom($this->dateFrom)
            ->setTo($this->dateTo);

        foreach ($items as $graphDataItem) {
            $control->addGraphDataItem($graphDataItem);
        }
        return $control;
    }

    public function createComponentGoogleSubscriptionsGraph(GoogleLineGraphGroupControlFactoryInterface $factory)
    {
        $this->getSession()->close();
        $items = [];

        $criteria = (new Criteria)
            ->setSeries(ActiveSubscriptionsMeasurement::CODE)
            ->setStart($this->dateFrom)
            ->setEnd($this->dateTo);
        $graphDataItem = new GraphDataItem();
        $graphDataItem->setCriteria($criteria)
            ->setScaleProvider(RangeScaleFactory::PROVIDER_MEASUREMENT)
            ->setName($this->translator->translate('dashboard.subscriptions.title'));
        $items[] = $graphDataItem;

        $criteria = (new Criteria)
            ->setSeries(ActiveSubscribersMeasurement::CODE)
            ->setStart($this->dateFrom)
            ->setEnd($this->dateTo);
        $graphDataItem = new GraphDataItem();
        $graphDataItem->setCriteria($criteria)
            ->setScaleProvider(RangeScaleFactory::PROVIDER_MEASUREMENT)
            ->setName($this->translator->translate('dashboard.users.subscribers'));
        $items[] = $graphDataItem;

        $criteria = (new Criteria)
            ->setSeries(ActivePayingSubscribersMeasurement::CODE)
            ->setStart($this->dateFrom)
            ->setEnd($this->dateTo);
        $graphDataItem = new GraphDataItem();
        $graphDataItem->setCriteria($criteria)
            ->setScaleProvider(RangeScaleFactory::PROVIDER_MEASUREMENT)
            ->setName($this->translator->translate('dashboard.users.paying_subscribers'));
        $items[] = $graphDataItem;

        $control = $factory->create()
            ->setGraphTitle($this->translator->translate('dashboard.users.new_or_subscribers.title'))
            ->setGraphHelp($this->translator->translate('dashboard.users.new_or_subscribers.tooltip'))
            ->setFrom($this->dateFrom)
            ->setTo($this->dateTo);

        foreach ($items as $graphDataItem) {
            $control->addGraphDataItem($graphDataItem);
        }
        return $control;
    }

    public function createComponentGoogleSubscriptionsStatsGraph(GoogleBarGraphGroupControlFactoryInterface $factory)
    {
        $this->getSession()->close();

        $graphDataItem = new GraphDataItem();
        $graphDataItem->setCriteria((new Criteria())
            ->setTableName('subscriptions')
            ->setGroupBy('subscription_types.name')
            ->setJoin('LEFT JOIN subscription_types ON subscription_types.id = subscriptions.subscription_type_id')
            ->setSeries('subscription_types.name')
            ->setValueField('count(*)')
            ->setStart($this->dateFrom)
            ->setEnd($this->dateTo));

        $control = $factory->create();
        $control->setGraphTitle($this->translator->translate('dashboard.subscriptions.by_type.title'))
            ->setGraphHelp($this->translator->translate('dashboard.subscriptions.by_type.tooltip'))
            ->addGraphDataItem($graphDataItem)
            ->setFrom($this->dateFrom)
            ->setTo($this->dateTo);

        return $control;
    }

    public function createComponentGoogleNewSubscriptionsStatsGraph(GoogleBarGraphGroupControlFactoryInterface $factory)
    {
        $this->getSession()->close();

        $graphDataItem = new GraphDataItem();
        $graphDataItem->setCriteria((new Criteria())
            ->setTableName('subscriptions')
            ->setGroupBy('subscription_types.name')
            ->setJoin('
                        LEFT JOIN subscription_types ON subscription_types.id = subscriptions.subscription_type_id
                        INNER JOIN payments ON payments.subscription_id = subscriptions.id
                        LEFT JOIN recurrent_payments ON recurrent_payments.payment_id = payments.id
                    ')
            ->setWhere('AND recurrent_payments.id IS NULL')
            ->setSeries('subscription_types.name')
            ->setValueField('count(*)')
            ->setStart($this->dateFrom)
            ->setEnd($this->dateTo));

        $control = $factory->create();
        $control->setGraphTitle($this->translator->translate('dashboard.subscriptions.only_new_by_type.title'))
            ->setGraphHelp($this->translator->translate('dashboard.subscriptions.only_new_by_type.tooltip'))
            ->addGraphDataItem($graphDataItem)
            ->setFrom($this->dateFrom)
            ->setTo($this->dateTo);

        return $control;
    }

    public function createComponentGoogleSubscriptionsEndGraph(GoogleLineGraphGroupControlFactoryInterface $factory)
    {
        $this->getSession()->close();
        $items = [];

        $graphDataItem = new GraphDataItem();
        $graphDataItem->setCriteria((new Criteria())
            ->setTableName('subscriptions')
            ->setTimeField('end_time')
            ->setValueField('count(*)')
            ->setStart($this->dateFrom)
            ->setEnd($this->dateTo));
        $graphDataItem->setName($this->translator->translate('dashboard.subscriptions.ending.now.title'));
        $items[] = $graphDataItem;

        $graphDataItem = new GraphDataItem();
        $graphDataItem->setCriteria((new Criteria())
            ->setTableName('subscriptions')
            ->setWhere('AND next_subscription_id IS NOT NULL')
            ->setTimeField('end_time')
            ->setValueField('count(*)')
            ->setStart($this->dateFrom)
            ->setEnd($this->dateTo));
        $graphDataItem->setName($this->translator->translate('dashboard.subscriptions.ending.withnext.title'));
        $items[] = $graphDataItem;

        /** @var EndingSubscriptionsDataProviderInterface[] $providers */
        $providers = $this->dataProviderManager->getProviders('subscriptions.dataprovider.ending_subscriptions', EndingSubscriptionsDataProviderInterface::class);
        foreach ($providers as $sorting => $provider) {
            $items[] = $provider->provide(['dateFrom' => $this->dateFrom, 'dateTo' => $this->dateTo]);
        }

        $control = $factory->create()
            ->setGraphTitle($this->translator->translate('dashboard.subscriptions.ending.title'))
            ->setGraphHelp($this->translator->translate('dashboard.subscriptions.ending.tooltip'))
            ->setFrom($this->dateFrom)
            ->setTo($this->dateTo);

        foreach ($items as $graphDataItem) {
            $control->addGraphDataItem($graphDataItem);
        }
        return $control;
    }

    public function createComponentSubscriptionEndsStats(SubscriptionEndsStatsFactoryInterface $factory)
    {
        $control = $factory->create();
        $control->setStartTime(DateTime::from($this->dateFrom));
        $control->setEndTime(DateTime::from($this->dateTo));
        $control->setWithoutNext(true);
        $control->setWithoutRecurrent(true);
        return $control;
    }

    public function createComponentGoogleAccessStatsGraph(GoogleLineGraphGroupControlFactoryInterface $factory)
    {
        $this->getSession()->close();
        $items = [];

        /** @var ActiveRow $contentAccess */
        foreach ($this->contentAccessRepository->all() as $contentAccess) {
            $graphDataItem = new GraphDataItem();
            $graphDataItem->setCriteria((new Criteria())
                ->setTableName('subscriptions')
                ->setRangeFields('start_time', 'end_time')
                ->setJoin(
                    <<<SQL
INNER JOIN subscription_types ON subscription_types.id = subscriptions.subscription_type_id
INNER JOIN subscription_type_content_access ON subscription_types.id = subscription_type_content_access.subscription_type_id
  AND subscription_type_content_access.content_access_id = {$contentAccess->id}
SQL
                )
                ->setValueField('count(distinct subscriptions.user_id)')
                ->setStart($this->dateFrom)
                ->setEnd($this->dateTo));
            $graphDataItem->setName($contentAccess->name);
            $items[] = $graphDataItem;
        }

        /** @var SubscriptionAccessStatsDataProviderInterface[] $providers */
        $providers = $this->dataProviderManager->getProviders('subscriptions.dataprovider.access_stats', SubscriptionAccessStatsDataProviderInterface::class);
        foreach ($providers as $sorting => $provider) {
            $graphDataItem = $provider->provide(['dateFrom' => $this->dateFrom, 'dateTo' => $this->dateTo]);
            $items[] = $graphDataItem;
        }

        $control = $factory->create();
        $control->setGraphTitle($this->translator->translate('dashboard.subscriptions.access.title'))
            ->setGraphHelp($this->translator->translate('dashboard.subscriptions.access.tooltip'))
            ->setFrom($this->dateFrom)
            ->setTo($this->dateTo);

        foreach ($items as $item) {
            $control->addGraphDataItem($item);
        }

        return $control;
    }
}
