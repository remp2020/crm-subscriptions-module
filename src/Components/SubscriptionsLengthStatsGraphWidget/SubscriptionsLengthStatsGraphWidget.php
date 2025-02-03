<?php

namespace Crm\SubscriptionsModule\Components\SubscriptionsLengthStatsGraphWidget;

use Crm\ApplicationModule\Components\Graphs\GoogleLineGraphGroup\GoogleLineGraphGroup;
use Crm\ApplicationModule\Components\Graphs\GoogleLineGraphGroup\GoogleLineGraphGroupControlFactoryInterface;
use Crm\ApplicationModule\Models\Graphs\Criteria;
use Crm\ApplicationModule\Models\Graphs\GraphDataItem;
use Crm\ApplicationModule\Models\Widget\BaseLazyWidget;
use Crm\ApplicationModule\Models\Widget\LazyWidgetManager;
use Nette\Localization\Translator;

class SubscriptionsLengthStatsGraphWidget extends BaseLazyWidget
{
    private string $templateName = 'subscriptions_length_stats_graph_widget.latte';

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
                ->setStart($this->getPresenter()->params['dateFrom'])
                ->setEnd($this->getPresenter()->params['dateTo']));

            $graphDataItem->setName(sprintf(
                "%s - %s %s",
                $range["from_days"],
                $range["to_days"],
                $this->translator->translate('subscriptions.components.subscriptions_length_stats_graph_widget.days')
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
            ->setStart($this->getPresenter()->params['dateFrom'])
            ->setEnd($this->getPresenter()->params['dateTo']));

        $graphDataItem->setName(sprintf(
            "%s %s",
            $dayRanges[array_key_last($dayRanges)]["to_days"] + 1,
            $this->translator->translate('subscriptions.components.subscriptions_length_stats_graph_widget.and_more_days')
        ));
        $items[] = $graphDataItem;

        $control = $factory->create()
            ->setGraphTitle($this->translator->translate('subscriptions.components.subscriptions_length_stats_graph_widget.title'))
            ->setGraphHelp($this->translator->translate('subscriptions.components.subscriptions_length_stats_graph_widget.tooltip'))
            ->setFrom($this->getPresenter()->params['dateFrom'])
            ->setTo($this->getPresenter()->params['dateTo']);

        foreach ($items as $graphDataItem) {
            $control->addGraphDataItem($graphDataItem);
        }

        return $control;
    }
}
