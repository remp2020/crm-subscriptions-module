<?php

namespace Crm\SubscriptionsModule\Components\AccessStatsGraphWidget;

use Crm\ApplicationModule\Components\Graphs\GoogleLineGraphGroup\GoogleLineGraphGroup;
use Crm\ApplicationModule\Components\Graphs\GoogleLineGraphGroup\GoogleLineGraphGroupControlFactoryInterface;
use Crm\ApplicationModule\Models\DataProvider\DataProviderManager;
use Crm\ApplicationModule\Models\Graphs\Criteria;
use Crm\ApplicationModule\Models\Graphs\GraphDataItem;
use Crm\ApplicationModule\Models\Widget\BaseLazyWidget;
use Crm\ApplicationModule\Models\Widget\LazyWidgetManager;
use Crm\SubscriptionsModule\DataProviders\SubscriptionAccessStatsDataProviderInterface;
use Crm\SubscriptionsModule\Repositories\ContentAccessRepository;
use Nette\Database\Table\ActiveRow;
use Nette\Localization\Translator;

class AccessStatsGraphWidget extends BaseLazyWidget
{
    private string $templateName = 'access_stats_graph_widget.latte';

    public function __construct(
        LazyWidgetManager $lazyWidgetManager,
        private readonly ContentAccessRepository $contentAccessRepository,
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
                ->setStart($this->getPresenter()->params['dateFrom'])
                ->setEnd($this->getPresenter()->params['dateTo']));
            $graphDataItem->setName($contentAccess->name);
            $items[] = $graphDataItem;
        }

        /** @var SubscriptionAccessStatsDataProviderInterface[] $providers */
        $providers = $this->dataProviderManager->getProviders('subscriptions.dataprovider.access_stats', SubscriptionAccessStatsDataProviderInterface::class);
        foreach ($providers as $sorting => $provider) {
            $graphDataItem = $provider->provide(['dateFrom' => $this->getPresenter()->params['dateFrom'], 'dateTo' => $this->getPresenter()->params['dateTo']]);
            $items[] = $graphDataItem;
        }

        $control = $factory->create();
        $control->setGraphTitle($this->translator->translate('subscriptions.components.access_stats_graph_widget.title'))
            ->setGraphHelp($this->translator->translate('subscriptions.components.access_stats_graph_widget.tooltip'))
            ->setFrom($this->getPresenter()->params['dateFrom'])
            ->setTo($this->getPresenter()->params['dateTo']);

        foreach ($items as $item) {
            $control->addGraphDataItem($item);
        }

        return $control;
    }
}
