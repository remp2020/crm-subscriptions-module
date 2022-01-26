<?php

namespace Crm\SubscriptionsModule\Components;

use Crm\ApplicationModule\Widget\BaseWidget;
use Crm\ApplicationModule\Widget\WidgetManager;
use Crm\SubscriptionsModule\Repository\SubscriptionsRepository;
use Nette\Localization\ITranslator;
use Nette\Utils\DateTime;

/**
 * This widget fetches subscriptions ending within different
 * time intervals and renders row with these values.
 *
 * @package Crm\SubscriptionsModule\Components
 */
class SubscriptionsEndingWithinPeriodWidget extends BaseWidget implements IWidgetLegend
{
    private $templateName = 'subscriptions_ending_within_period_widget.latte';

    private $subscriptionsRepository;

    private $translator;

    public function __construct(
        WidgetManager $widgetManager,
        SubscriptionsRepository $subscriptionsRepository,
        ITranslator $translator
    ) {
        parent::__construct($widgetManager);
        $this->subscriptionsRepository = $subscriptionsRepository;
        $this->translator = $translator;
    }

    public function legend(): string
    {
        return sprintf('<span class="text-warning">%s</span>', $this->translator->translate('dashboard.subscriptions.ending.now.title'));
    }

    public function identifier()
    {
        return 'subscriptionsnedingwithinperiod';
    }

    public function render()
    {
        $this->template->subscriptionsEndToday = $this->subscriptionsRepository
            ->subscriptionsEndingBetween(DateTime::from('today 00:00'), DateTime::from('today 23:59:59'))
            ->count('*');
        $this->template->subscriptionsEndTomorow = $this->subscriptionsRepository
            ->subscriptionsEndingBetween(DateTime::from('tomorrow 00:00'), DateTime::from('tomorrow 23:59:59'))
            ->count('*');
        $this->template->subscriptionsEndAfterTomorow = $this->subscriptionsRepository
            ->subscriptionsEndingBetween(DateTime::from('+2 days 00:00'), DateTime::from('+2 days 23:59:59'))
            ->count('*');
        $this->template->subscriptionsEndInOneWeek = $this->subscriptionsRepository
            ->subscriptionsEndingBetween(DateTime::from('today 00:00'), DateTime::from('+7 days 23:59:59'))
            ->count('*');
        $this->template->subscriptionsEndInTwoWeeks = $this->subscriptionsRepository
            ->subscriptionsEndingBetween(DateTime::from('today 00:00'), DateTime::from('+14 days 23:59:59'))
            ->count('*');
        $this->template->subscriptionsEndInOneMonth = $this->subscriptionsRepository
            ->subscriptionsEndingBetween(DateTime::from('today 00:00'), DateTime::from('+31 days 23:59:59'))
            ->count('*');

        $this->template->setFile(__DIR__ . DIRECTORY_SEPARATOR . $this->templateName);
        $this->template->render();
    }
}
