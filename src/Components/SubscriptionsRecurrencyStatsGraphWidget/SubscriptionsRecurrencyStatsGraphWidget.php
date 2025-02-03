<?php

namespace Crm\SubscriptionsModule\Components\SubscriptionsRecurrencyStatsGraphWidget;

use Crm\ApplicationModule\Components\Graphs\GoogleLineGraphGroup\GoogleLineGraphGroup;
use Crm\ApplicationModule\Components\Graphs\GoogleLineGraphGroup\GoogleLineGraphGroupControlFactoryInterface;
use Crm\ApplicationModule\Models\Graphs\Criteria;
use Crm\ApplicationModule\Models\Graphs\GraphDataItem;
use Crm\ApplicationModule\Models\Widget\BaseLazyWidget;
use Crm\ApplicationModule\Models\Widget\LazyWidgetManager;
use Nette\Localization\Translator;

class SubscriptionsRecurrencyStatsGraphWidget extends BaseLazyWidget
{
    private string $templateName = 'subscriptions_recurrency_stats_graph_widget.latte';

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

        $graphDataItem = new GraphDataItem();
        $graphDataItem->setCriteria((new Criteria())
            ->setTableName('subscriptions')
            ->setRangeFields('start_time', 'end_time')
            ->setValueField('count(distinct subscriptions.user_id)')
            ->setWhere('AND is_recurrent = 1 AND is_paid = 1')
            ->setStart($this->getPresenter()->params['dateFrom'])
            ->setEnd($this->getPresenter()->params['dateTo']));
        $graphDataItem->setName($this->translator->translate('subscriptions.components.subscriptions_recurrency_stats_graph_widget.recurrent_subscribers'));
        $items[] = $graphDataItem;

        $graphDataItem = new GraphDataItem();
        $graphDataItem->setCriteria((new Criteria())
            ->setTableName('subscriptions')
            ->setRangeFields('start_time', 'end_time')
            ->setValueField('count(distinct subscriptions.user_id)')
            ->setWhere('AND is_recurrent = 0 AND is_paid = 1')
            ->setStart($this->getPresenter()->params['dateFrom'])
            ->setEnd($this->getPresenter()->params['dateTo']));
        $graphDataItem->setName($this->translator->translate('subscriptions.components.subscriptions_recurrency_stats_graph_widget.nonrecurrent_subscribers'));
        $items[] = $graphDataItem;

        $control = $factory->create()
            ->setGraphTitle($this->translator->translate('subscriptions.components.subscriptions_recurrency_stats_graph_widget.title'))
            ->setGraphHelp($this->translator->translate('subscriptions.components.subscriptions_recurrency_stats_graph_widget.tooltip'))
            ->setFrom($this->getPresenter()->params['dateFrom'])
            ->setTo($this->getPresenter()->params['dateTo']);

        foreach ($items as $graphDataItem) {
            $control->addGraphDataItem($graphDataItem);
        }

        return $control;
    }
}
