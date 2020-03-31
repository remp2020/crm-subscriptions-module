<?php

namespace Crm\SubscriptionsModule\Presenters;

use Crm\AdminModule\Presenters\AdminPresenter;
use Crm\ApplicationModule\Components\Graphs\GoogleLineGraphGroupControlFactoryInterface;
use Crm\ApplicationModule\Graphs\Criteria;
use Crm\ApplicationModule\Graphs\GraphDataItem;
use Crm\SubscriptionsModule\Forms\SubscriptionTypeItemsFormFactory;
use Crm\SubscriptionsModule\Forms\SubscriptionTypesFormFactory;
use Crm\SubscriptionsModule\Repository\SubscriptionTypeItemsRepository;
use Crm\SubscriptionsModule\Repository\SubscriptionTypesRepository;

class SubscriptionTypesAdminPresenter extends AdminPresenter
{
    private $subscriptionTypesRepository;

    private $subscriptionTypeFactory;

    private $subscriptionTypeItemsRepository;

    private $subscriptionTypeItemsFormFactory;


    public function __construct(
        SubscriptionTypesRepository $subscriptionTypesRepository,
        SubscriptionTypesFormFactory $subscriptionTypeFactory,
        SubscriptionTypeItemsRepository $subscriptionTypeItemsRepository,
        SubscriptionTypeItemsFormFactory $subscriptionTypeItemsFormFactory
    ) {
        parent::__construct();
        $this->subscriptionTypesRepository = $subscriptionTypesRepository;
        $this->subscriptionTypeFactory = $subscriptionTypeFactory;
        $this->subscriptionTypeItemsRepository = $subscriptionTypeItemsRepository;
        $this->subscriptionTypeItemsFormFactory = $subscriptionTypeItemsFormFactory;
    }

    public function renderDefault()
    {
        $subscriptionTypes = $this->filteredSubscriptionTypes();
        $activeSubscriptionTypes = [];
        $inactiveSubscriptionTypes = [];
        foreach ($subscriptionTypes as $subscriptionType) {
            if ($subscriptionType->visible) {
                $activeSubscriptionTypes[] = $subscriptionType;
            } else {
                $inactiveSubscriptionTypes[] = $subscriptionType;
            }
        }

        $this->template->activeSubscriptionTypes = $activeSubscriptionTypes;
        $this->template->inactiveSubscriptionTypes = $inactiveSubscriptionTypes;

        $this->template->totalSubscriptionTypes = $this->subscriptionTypesRepository->totalCount();
    }

    private function filteredSubscriptionTypes()
    {
        return $this->subscriptionTypesRepository->all($this->text)->order('sorting ASC');
    }

    public function renderNew()
    {
    }

    public function renderShow($id)
    {
        $subscriptionType = $this->subscriptionTypesRepository->find($id);
        if (!$subscriptionType) {
            $this->flashMessage($this->translator->translate('subscriptions.admin.subscription_types.messages.subscription_type_not_found'));
            $this->redirect('default');
        }
        $this->template->type = $subscriptionType;
        $this->template->subscriptionTypeItems = $this->subscriptionTypeItemsRepository->subscriptionTypeItems($subscriptionType);
    }

    protected function createComponentSubscriptionTypeItemsForm()
    {
        $form = $this->subscriptionTypeItemsFormFactory->create($this->params['id']);
        $this->subscriptionTypeItemsFormFactory->onSave = function ($subscriptionTypeItem) {
            $this->flashMessage($this->translator->translate('subscriptions.admin.subscription_types.messages.subscription_type_item_created'));
            $this->redirect('SubscriptionTypesAdmin:Show', $subscriptionTypeItem->subscription_type_id);
        };
        return $form;
    }

    public function handleRemoveSubscriptionTypeItem($itemId)
    {
        $item = $this->subscriptionTypeItemsRepository->find($itemId);
        $subscriptionTypeId = $item->subscription_type_id;
        $this->subscriptionTypeItemsRepository->delete($item);
        $this->flashMessage($this->translator->translate('subscriptions.admin.subscription_type.messages.subscription_type_item_deleted'));
        if ($this->isAjax()) {
            $this->redrawControl('subscriptionTypeItemsSnippet');
        } else {
            $this->redirect('show', $subscriptionTypeId);
        }
    }

    public function renderEdit($id)
    {
        $subscriptionType = $this->subscriptionTypesRepository->find($id);
        if (!$subscriptionType) {
            $this->flashMessage($this->translator->translate('subscriptions.admin.subscription_types.messages.subscription_type_not_found'));
            $this->redirect('default');
        }
        $this->template->type = $subscriptionType;
    }

    protected function createComponentSubscriptionTypeForm()
    {
        $id = null;
        if (isset($this->params['id'])) {
            $id = $this->params['id'];
        }

        $form = $this->subscriptionTypeFactory->create($id);

        $this->subscriptionTypeFactory->onSave = function ($subscriptionType) {
            $this->flashMessage($this->translator->translate('subscriptions.admin.subscription_types.messages.subscription_type_created'));
            $this->redirect('SubscriptionTypesAdmin:Show', $subscriptionType->id);
        };
        $this->subscriptionTypeFactory->onUpdate = function ($subscriptionType) {
            $this->flashMessage($this->translator->translate('subscriptions.admin.subscription_types.messages.subscription_type_updated'));
            $this->redirect('SubscriptionTypesAdmin:Show', $subscriptionType->id);
        };
        return $form;
    }

    protected function createComponentSubscriptionsGraph(GoogleLineGraphGroupControlFactoryInterface $factory)
    {
        $graphDataItem1 = new GraphDataItem();
        $graphDataItem1->setCriteria((new Criteria())
            ->setTableName('subscriptions')
            ->setTimeField('created_at')
            ->setWhere('AND subscription_type_id=' . intval($this->params['id']))
            ->setValueField('COUNT(*)')
            ->setStart('-1 month'))
            ->setName('Created subscriptions');

        $control = $factory->create()
            ->setGraphTitle('New subscriptions')
            ->setGraphHelp('New subscriptions created in time')
            ->addGraphDataItem($graphDataItem1);

        return $control;
    }

    public function renderExport()
    {
        $this->getHttpResponse()->addHeader('Content-Type', 'application/csv');
        $this->getHttpResponse()->addHeader('Content-Disposition', 'attachment; filename=export.csv');

        $this->template->types = $this->subscriptionTypesRepository->all();
    }
}
