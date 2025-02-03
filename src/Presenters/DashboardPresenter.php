<?php

namespace Crm\SubscriptionsModule\Presenters;

use Crm\AdminModule\Components\DateFilterFormFactory;
use Crm\AdminModule\Presenters\AdminPresenter;
use Crm\ApplicationModule\Models\DataProvider\DataProviderManager;
use Nette\Application\Attributes\Persistent;
use Nette\DI\Attributes\Inject;
use Nette\Utils\DateTime;

class DashboardPresenter extends AdminPresenter
{
    #[Inject]
    public DataProviderManager $dataProviderManager;

    #[Persistent]
    public $dateFrom;

    #[Persistent]
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
}
