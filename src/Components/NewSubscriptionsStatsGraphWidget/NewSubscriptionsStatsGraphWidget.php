<?php

namespace Crm\SubscriptionsModule\Components\NewSubscriptionsStatsGraphWidget;

use Crm\ApplicationModule\Components\Graphs\GoogleBarGraphGroup\GoogleBarGraphGroup;
use Crm\ApplicationModule\Components\Graphs\GoogleBarGraphGroup\GoogleBarGraphGroupControlFactoryInterface;
use Crm\ApplicationModule\Models\Graphs\Criteria;
use Crm\ApplicationModule\Models\Graphs\GraphDataItem;
use Crm\ApplicationModule\Models\Widget\BaseLazyWidget;
use Crm\ApplicationModule\Models\Widget\LazyWidgetManager;
use Nette\Localization\Translator;

class NewSubscriptionsStatsGraphWidget extends BaseLazyWidget
{
    private string $templateName = 'new_subscriptions_stats_graph_widget.latte';

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

    public function createComponentGraph(GoogleBarGraphGroupControlFactoryInterface $factory): GoogleBarGraphGroup
    {
        $this->getPresenter()->getSession()->close();

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
            ->setStart($this->getPresenter()->params['dateFrom'])
            ->setEnd($this->getPresenter()->params['dateTo']));

        $control = $factory->create();
        $control->setGraphTitle($this->translator->translate('subscriptions.components.new_subscriptions_stats_graph_widget.title'))
            ->setGraphHelp($this->translator->translate('subscriptions.components.new_subscriptions_stats_graph_widget.tooltip'))
            ->addGraphDataItem($graphDataItem)
            ->setFrom($this->getPresenter()->params['dateFrom'])
            ->setTo($this->getPresenter()->params['dateTo']);

        return $control;
    }
}
