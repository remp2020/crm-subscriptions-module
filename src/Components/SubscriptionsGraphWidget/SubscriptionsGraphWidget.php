<?php

namespace Crm\SubscriptionsModule\Components\SubscriptionsGraphWidget;

use Crm\ApplicationModule\Components\Graphs\GoogleLineGraphGroup\GoogleLineGraphGroup;
use Crm\ApplicationModule\Components\Graphs\GoogleLineGraphGroup\GoogleLineGraphGroupControlFactoryInterface;
use Crm\ApplicationModule\Models\Graphs\Criteria;
use Crm\ApplicationModule\Models\Graphs\GraphDataItem;
use Crm\ApplicationModule\Models\Graphs\Scale\Measurements\RangeScaleFactory;
use Crm\ApplicationModule\Models\Widget\BaseLazyWidget;
use Crm\ApplicationModule\Models\Widget\LazyWidgetManager;
use Crm\SubscriptionsModule\Measurements\ActivePayingSubscribersMeasurement;
use Crm\SubscriptionsModule\Measurements\ActiveSubscribersMeasurement;
use Crm\SubscriptionsModule\Measurements\ActiveSubscriptionsMeasurement;
use Nette\Localization\Translator;

class SubscriptionsGraphWidget extends BaseLazyWidget
{
    private string $templateName = 'subscriptions_graph_widget.latte';

    public function __construct(
        LazyWidgetManager $lazyWidgetManager,
        private readonly Translator $translator,
    ) {
        parent::__construct($lazyWidgetManager);
    }

    public function render(): void
    {
        $this->template->setFile(__DIR__ . DIRECTORY_SEPARATOR . $this->templateName);
        $this->template->render();
    }

    public function createComponentGraph(GoogleLineGraphGroupControlFactoryInterface $factory): GoogleLineGraphGroup
    {
        $this->getPresenter()->getSession()->close();

        $items = [];

        $criteria = (new Criteria)
            ->setSeries(ActiveSubscriptionsMeasurement::CODE)
            ->setStart($this->getPresenter()->params['dateFrom'])
            ->setEnd($this->getPresenter()->params['dateTo']);
        $graphDataItem = new GraphDataItem();
        $graphDataItem->setCriteria($criteria)
            ->setScaleProvider(RangeScaleFactory::PROVIDER_MEASUREMENT)
            ->setName($this->translator->translate('dashboard.subscriptions.title'));
        $items[] = $graphDataItem;

        $criteria = (new Criteria)
            ->setSeries(ActiveSubscribersMeasurement::CODE)
            ->setStart($this->getPresenter()->params['dateFrom'])
            ->setEnd($this->getPresenter()->params['dateTo']);
        $graphDataItem = new GraphDataItem();
        $graphDataItem->setCriteria($criteria)
            ->setScaleProvider(RangeScaleFactory::PROVIDER_MEASUREMENT)
            ->setName($this->translator->translate('dashboard.users.subscribers'));
        $items[] = $graphDataItem;

        $criteria = (new Criteria)
            ->setSeries(ActivePayingSubscribersMeasurement::CODE)
            ->setStart($this->getPresenter()->params['dateFrom'])
            ->setEnd($this->getPresenter()->params['dateTo']);
        $graphDataItem = new GraphDataItem();
        $graphDataItem->setCriteria($criteria)
            ->setScaleProvider(RangeScaleFactory::PROVIDER_MEASUREMENT)
            ->setName($this->translator->translate('dashboard.users.paying_subscribers'));
        $items[] = $graphDataItem;

        $control = $factory->create()
            ->setGraphTitle($this->translator->translate('dashboard.users.new_or_subscribers.title'))
            ->setGraphHelp($this->translator->translate('dashboard.users.new_or_subscribers.tooltip'))
            ->setFrom($this->getPresenter()->params['dateFrom'])
            ->setTo($this->getPresenter()->params['dateTo']);

        foreach ($items as $graphDataItem) {
            $control->addGraphDataItem($graphDataItem);
        }

        return $control;
    }
}
