<?php

namespace Crm\SubscriptionsModule\Presenters;

use Crm\AdminModule\Presenters\AdminPresenter;
use Crm\ApplicationModule\Components\Graphs\GoogleLineGraphGroupControlFactoryInterface;
use Crm\ApplicationModule\Graphs\Criteria;
use Crm\ApplicationModule\Graphs\GraphDataItem;
use Crm\SubscriptionsModule\Forms\AdminFilterFormFactory;
use Crm\SubscriptionsModule\Forms\SubscriptionTypeItemsFormFactory;
use Crm\SubscriptionsModule\Forms\SubscriptionTypeMetaFormFactory;
use Crm\SubscriptionsModule\Forms\SubscriptionTypesFormFactory;
use Crm\SubscriptionsModule\Models\AdminFilterFormData;
use Crm\SubscriptionsModule\Repository\ContentAccessRepository;
use Crm\SubscriptionsModule\Repository\SubscriptionTypeItemMetaRepository;
use Crm\SubscriptionsModule\Repository\SubscriptionTypeItemsRepository;
use Crm\SubscriptionsModule\Repository\SubscriptionTypesMetaRepository;
use Crm\SubscriptionsModule\Repository\SubscriptionTypesRepository;

class SubscriptionTypesAdminPresenter extends AdminPresenter
{
    /** @persistent */
    public $formData = [];

    private $subscriptionType;

    public function __construct(
        private ContentAccessRepository $contentAccessRepository,
        private SubscriptionTypesRepository $subscriptionTypesRepository,
        private SubscriptionTypesFormFactory $subscriptionTypesFormFactory,
        private SubscriptionTypeItemsRepository $subscriptionTypeItemsRepository,
        private SubscriptionTypeItemsFormFactory $subscriptionTypeItemsFormFactory,
        private SubscriptionTypesMetaRepository $subscriptionTypesMetaRepository,
        private SubscriptionTypeMetaFormFactory $subscriptionTypeMetaFormFactory,
        private SubscriptionTypeItemMetaRepository $subscriptionTypeItemMetaRepository,
        private AdminFilterFormFactory $adminFilterFormFactory,
        private AdminFilterFormData $adminFilterFormData,
    ) {
        parent::__construct();
    }

    public function startup()
    {
        parent::startup();
        $this->adminFilterFormData->parse($this->formData);
    }

    /**
     * @admin-access-level read
     */
    public function renderDefault()
    {
        $subscriptionTypes = $this->adminFilterFormData->getFilteredSubscriptionTypes();

        $visibleSubscriptionTypes = [];
        $invisibleSubscriptionTypes = [];
        foreach ($subscriptionTypes as $subscriptionType) {
            if ($subscriptionType->visible) {
                $visibleSubscriptionTypes[] = $subscriptionType;
            } else {
                $invisibleSubscriptionTypes[] = $subscriptionType;
            }
        }

        $this->template->activeSubscriptionTypes = $visibleSubscriptionTypes;
        $this->template->inactiveSubscriptionTypes = $invisibleSubscriptionTypes;

        $this->template->totalSubscriptionTypes = $subscriptionTypes->count();
    }

    private function filteredSubscriptionTypes()
    {
        return $this->subscriptionTypesRepository->all()->order('sorting ASC');
    }

    public function createComponentAdminFilterForm()
    {
        $form = $this->adminFilterFormFactory->create();
        $form->setDefaults($this->adminFilterFormData->getFormValues());

        $this->adminFilterFormFactory->onFilter = function (array $values) {
            $this->redirect($this->action, ['formData' => array_map(function ($item) {
                return $item ?: null;
            }, $values)]);
        };
        $this->adminFilterFormFactory->onCancel = function (array $emptyValues) {
            $this->redirect($this->action, ['formData' => $emptyValues]);
        };

        return $form;
    }

    /**
     * @admin-access-level write
     */
    public function renderNew()
    {
    }

    /**
     * @admin-access-level read
     */
    public function actionShow($id)
    {
        $this->subscriptionType = $this->subscriptionTypesRepository->find($id);
        if (!$this->subscriptionType) {
            $this->flashMessage($this->translator->translate('subscriptions.admin.subscription_types.messages.subscription_type_not_found'), 'error');
            $this->redirect('default');
        }
        $this->template->type = $this->subscriptionType;
        $this->template->subscriptionTypeItems = $this->subscriptionTypeItemsRepository->subscriptionTypeItems($this->subscriptionType);
        $this->template->meta = $this->subscriptionTypesMetaRepository->getAllBySubscriptionType($this->subscriptionType)->order('key ASC');
    }

    /**
     * @admin-access-level read
     */
    public function actionStats($id)
    {
        $this->subscriptionType = $this->subscriptionTypesRepository->find($id);
        if (!$this->subscriptionType) {
            $this->flashMessage($this->translator->translate('subscriptions.admin.subscription_types.messages.subscription_type_not_found'), 'error');
            $this->redirect('default');
        }
        $this->template->type = $this->subscriptionType;
    }

    protected function createComponentSubscriptionTypeItemsForm()
    {
        $form = $this->subscriptionTypeItemsFormFactory->create();
        $this->subscriptionTypeItemsFormFactory->onSave = function ($subscriptionTypeItem) {
            $this->flashMessage($this->translator->translate('subscriptions.admin.subscription_types.messages.subscription_type_item_created'));
            $this->redirect('SubscriptionTypesAdmin:Show', $subscriptionTypeItem->subscription_type_id);
        };
        return $form;
    }

    /**
     * @admin-access-level write
     */
    public function handleRemoveSubscriptionTypeItem($itemId)
    {
        $item = $this->subscriptionTypeItemsRepository->find($itemId);
        $subscriptionTypeId = $item->subscription_type_id;
        $this->subscriptionTypeItemsRepository->softDelete($item);
        $this->flashMessage($this->translator->translate('subscriptions.admin.subscription_type.messages.subscription_type_item_deleted'));
        if ($this->isAjax()) {
            $this->redrawControl('subscriptionTypeItemsSnippet');
        } else {
            $this->redirect('show', $subscriptionTypeId);
        }
    }

    protected function createComponentSubscriptionTypeMetaForm()
    {
        $form = $this->subscriptionTypeMetaFormFactory->create($this->subscriptionType);
        $this->subscriptionTypeMetaFormFactory->onSave = function ($meta) {
            $this->flashMessage($this->translator->translate('subscriptions.admin.subscription_types_meta.value_added'));
            if ($this->isAjax()) {
                $this->redrawControl('subscriptionTypeMetaSnippet');
            } else {
                $this->redirect('show', $meta['subscription_type_id']);
            }
        };
        $this->subscriptionTypeMetaFormFactory->onError = function () {
            if ($this->isAjax()) {
                $this->redrawControl('metaFormSnippet');
            }
        };
        return $form;
    }

    /**
     * @admin-access-level write
     */
    public function handleRemoveSubscriptionTypeMeta($metaId)
    {
        $meta = $this->subscriptionTypesMetaRepository->find($metaId);
        $subscriptionTypeId = $meta['subscription_type_id'];
        $this->subscriptionTypesMetaRepository->delete($meta);
        $this->flashMessage($this->translator->translate('subscriptions.admin.subscription_types_meta.value_removed'));
        if ($this->isAjax()) {
            $this->redrawControl('subscriptionTypeMetaSnippet');
        } else {
            $this->redirect('show', $subscriptionTypeId);
        }
    }

    /**
     * @admin-access-level write
     */
    public function renderEdit($id)
    {
        $subscriptionType = $this->subscriptionTypesRepository->find($id);
        if (!$subscriptionType) {
            $this->flashMessage($this->translator->translate('subscriptions.admin.subscription_types.messages.subscription_type_not_found'), 'error');
            $this->redirect('default');
        }
        if ($this->subscriptionTypeItemMetaRepository->subscriptionTypeItemsHaveMeta($subscriptionType)) {
            $this->flashMessage($this->translator->translate('subscriptions.admin.subscription_types.messages.subscription_type_not_editable'), 'error');
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

        $form = $this->subscriptionTypesFormFactory->create($id);

        $this->subscriptionTypesFormFactory->onSave = function ($subscriptionType) {
            $this->flashMessage($this->translator->translate('subscriptions.admin.subscription_types.messages.subscription_type_created'));
            $this->redirect('SubscriptionTypesAdmin:Show', $subscriptionType->id);
        };
        $this->subscriptionTypesFormFactory->onUpdate = function ($subscriptionType) {
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
            ->setWhere('AND subscription_type_id=' . (int)$this->params['id'])
            ->setValueField('COUNT(*)')
            ->setStart('-1 month'))
            ->setName('Created subscriptions');

        $control = $factory->create()
            ->setGraphTitle($this->translator->translate('subscriptions.admin.subscriptions_graph.title'))
            ->setGraphHelp($this->translator->translate('subscriptions.admin.subscriptions_graph.help'))
            ->addGraphDataItem($graphDataItem1);

        return $control;
    }

    /**
     * @admin-access-level read
     */
    public function renderExport()
    {
        $this->getHttpResponse()->addHeader('Content-Type', 'application/csv');
        $this->getHttpResponse()->addHeader('Content-Disposition', 'attachment; filename=export.csv');

        $contentAccesses = $this->contentAccessRepository->getTable()
            ->order('sorting')
            ->fetchPairs('name', 'name');

        $columns = ['id', 'name', 'code', 'price', 'length'];
        $columns = array_merge($columns, $contentAccesses);
        // fill values with 0 so missing content accesses are printed as 0
        $columns = array_fill_keys($columns, 0);

        $subscriptionTypes = [];
        foreach ($this->subscriptionTypesRepository->all() as $subscriptionTypeRow) {
            // prefill subscription type also with empty content accesses
            $subscriptionType = $columns;

            // set subscription type data
            $subscriptionType['id'] = $subscriptionTypeRow->id;
            $subscriptionType['name'] = $subscriptionTypeRow->name;
            $subscriptionType['code'] = $subscriptionTypeRow->code;
            $subscriptionType['price'] = $subscriptionTypeRow->price;
            $subscriptionType['length'] = $subscriptionTypeRow->length;

            // set content accesses data
            $subscriptionTypeContentAccesses = $subscriptionTypeRow
                ->related('subscription_type_content_access')
                ->fetchAll();
            foreach ($subscriptionTypeContentAccesses as $stca) {
                $subscriptionType[$stca->content_access->name] = true;
            }

            $subscriptionTypes[] = $subscriptionType;
        }

        // format all for csv
        $data = "";
        foreach ($columns as $column => $_) {
            $data .= '"' . $column . '";';
        }
        $data .= PHP_EOL;
        foreach ($subscriptionTypes as $subscriptionType) {
            foreach ($columns as $column => $_) {
                $data .= '"' . $subscriptionType[$column] . '";';
            }
            $data .= PHP_EOL;
        }

        $this->template->data = $data;
    }
}
