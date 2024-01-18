<?php

namespace Crm\SubscriptionsModule\Components\MonthSubscriptionsSmallBarGraphWidget;

use Crm\ApplicationModule\Components\Graphs\SmallBarGraphControlFactoryInterface;
use Crm\ApplicationModule\Graphs\Criteria;
use Crm\ApplicationModule\Graphs\GraphData;
use Crm\ApplicationModule\Graphs\GraphDataItem;
use Crm\ApplicationModule\Widget\BaseLazyWidget;
use Crm\ApplicationModule\Widget\LazyWidgetManager;
use Nette\Localization\Translator;

/**
 * This widget uses graph data to fetch subscriptions from last 31 days
 * and renders simple small graph.
 *
 * @package Crm\SubscriptionsModule\Components
 */
class MonthSubscriptionsSmallBarGraphWidget extends BaseLazyWidget
{
    private $templateName = 'month_subscriptions_small_bar_graph_widget.latte';

    private $factory;

    private $graphData;

    private $translator;

    public function __construct(
        LazyWidgetManager $lazyWidgetManager,
        SmallBarGraphControlFactoryInterface $factory,
        GraphData $graphData,
        Translator $translator
    ) {
        parent::__construct($lazyWidgetManager);
        $this->factory = $factory;
        $this->graphData = $graphData;
        $this->translator = $translator;
    }

    public function identifier()
    {
        return 'monthsubscriptionssmallbargraphwidget';
    }

    public function render()
    {
        $this->template->setFile(__DIR__ . DIRECTORY_SEPARATOR . $this->templateName);
        $this->template->render();
    }

    protected function createComponentSubscriptionsSmallBarGraph()
    {
        $graphDataItem = new GraphDataItem();
        $graphDataItem
            ->setCriteria(
                (new Criteria())
                    ->setStart('-31 days')
                    ->setTableName('subscriptions')
            );

        $this->graphData->addGraphDataItem($graphDataItem);
        $this->graphData->setScaleRange('day');

        $control = $this->factory->create();
        $control->setGraphTitle($this->translator->translate('subscriptions.admin.month_subscriptions_small_bar_graph_widget.title'))
            ->addSerie($this->graphData->getData());
        return $control;
    }
}
