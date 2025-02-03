<?php

namespace Crm\SubscriptionsModule\Components\SubscriptionsEndGraphWidget;

use Crm\ApplicationModule\Components\Graphs\GoogleLineGraphGroup\GoogleLineGraphGroup;
use Crm\ApplicationModule\Components\Graphs\GoogleLineGraphGroup\GoogleLineGraphGroupControlFactoryInterface;
use Crm\ApplicationModule\Models\DataProvider\DataProviderManager;
use Crm\ApplicationModule\Models\Graphs\Criteria;
use Crm\ApplicationModule\Models\Graphs\GraphDataItem;
use Crm\ApplicationModule\Models\Widget\BaseLazyWidget;
use Crm\ApplicationModule\Models\Widget\LazyWidgetManager;
use Crm\SubscriptionsModule\DataProviders\EndingSubscriptionsDataProviderInterface;
use Nette\Localization\Translator;

class SubscriptionsEndGraphWidget extends BaseLazyWidget
{
    private string $templateName = 'subscriptions_end_graph_widget.latte';

    public function __construct(
        LazyWidgetManager $lazyWidgetManager,
        private readonly DataProviderManager $dataProviderManager,
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
            ->setTimeField('end_time')
            ->setValueField('count(*)')
            ->setStart($this->getPresenter()->params['dateFrom'])
            ->setEnd($this->getPresenter()->params['dateTo']));
        $graphDataItem->setName($this->translator->translate('dashboard.subscriptions.ending.now.title'));
        $items[] = $graphDataItem;

        $graphDataItem = new GraphDataItem();
        $graphDataItem->setCriteria((new Criteria())
            ->setTableName('subscriptions')
            ->setWhere('AND next_subscription_id IS NOT NULL')
            ->setTimeField('end_time')
            ->setValueField('count(*)')
            ->setStart($this->getPresenter()->params['dateFrom'])
            ->setEnd($this->getPresenter()->params['dateTo']));
        $graphDataItem->setName($this->translator->translate('dashboard.subscriptions.ending.withnext.title'));
        $items[] = $graphDataItem;

        /** @var EndingSubscriptionsDataProviderInterface[] $providers */
        $providers = $this->dataProviderManager->getProviders('subscriptions.dataprovider.ending_subscriptions', EndingSubscriptionsDataProviderInterface::class);
        foreach ($providers as $sorting => $provider) {
            $items[] = $provider->provide(['dateFrom' => $this->getPresenter()->params['dateFrom'], 'dateTo' => $this->getPresenter()->params['dateTo']]);
        }

        $control = $factory->create()
            ->setGraphTitle($this->translator->translate('dashboard.subscriptions.ending.title'))
            ->setGraphHelp($this->translator->translate('dashboard.subscriptions.ending.tooltip'))
            ->setFrom($this->getPresenter()->params['dateFrom'])
            ->setTo($this->getPresenter()->params['dateTo']);

        foreach ($items as $graphDataItem) {
            $control->addGraphDataItem($graphDataItem);
        }

        return $control;
    }
}
