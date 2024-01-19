<?php

namespace Crm\SubscriptionsModule\Components\RenewedSubscriptionsEndingWithinPeriodWidget;

use Crm\ApplicationModule\Models\Widget\BaseLazyWidget;
use Crm\ApplicationModule\Models\Widget\LazyWidgetManager;
use Crm\SubscriptionsModule\Components\WidgetLegendInterface;
use Crm\SubscriptionsModule\Repositories\SubscriptionsRepository;
use Nette\Localization\Translator;
use Nette\Utils\DateTime;

/**
 * This widget fetches renewed subscriptions for intervals
 * and renders line with these values.
 *
 * @package Crm\SubscriptionsModule\Components
 */
class RenewedSubscriptionsEndingWithinPeriodWidget extends BaseLazyWidget implements WidgetLegendInterface
{
    private $templateName = 'renewed_subscriptions_ending_within_period_widget.latte';

    private $subscriptionsRepository;

    private $translator;

    public function __construct(
        LazyWidgetManager $lazyWidgetManager,
        SubscriptionsRepository $subscriptionsRepository,
        Translator $translator
    ) {
        parent::__construct($lazyWidgetManager);
        $this->subscriptionsRepository = $subscriptionsRepository;
        $this->translator = $translator;
    }

    public function legend(): string
    {
        return sprintf('<span class="text-success">%s</span>', $this->translator->translate('dashboard.subscriptions.ending.withnext.title'));
    }

    public function identifier()
    {
        return 'renewedsubscriptionsnedingwithinperiod';
    }

    public function render()
    {
        $this->template->subscriptionsRenewedToday = $this->subscriptionsRepository
            ->renewedSubscriptionsEndingBetween(DateTime::from('today 00:00'), DateTime::from('today 23:59:59'))
            ->count('*');
        $this->template->subscriptionsRenewedTomorow = $this->subscriptionsRepository
            ->renewedSubscriptionsEndingBetween(DateTime::from('tomorrow 00:00'), DateTime::from('tomorrow 23:59:59'))
            ->count('*');
        $this->template->subscriptionsRenewedAfterTomorow = $this->subscriptionsRepository
            ->renewedSubscriptionsEndingBetween(DateTime::from('+2 days 00:00'), DateTime::from('+2 days 23:59:59'))
            ->count('*');
        $this->template->subscriptionsRenewedInOneWeek = $this->subscriptionsRepository
            ->renewedSubscriptionsEndingBetween(DateTime::from('today 00:00'), DateTime::from('+7 days 23:59:59'))
            ->count('*');
        $this->template->subscriptionsRenewedInTwoWeeks = $this->subscriptionsRepository
            ->renewedSubscriptionsEndingBetween(DateTime::from('today 00:00'), DateTime::from('+14 days 23:59:59'))
            ->count('*');
        $this->template->subscriptionsRenewedInOneMonth = $this->subscriptionsRepository
            ->renewedSubscriptionsEndingBetween(DateTime::from('today 00:00'), DateTime::from('+31 days 23:59:59'))
            ->count('*');

        $this->template->setFile(__DIR__ . DIRECTORY_SEPARATOR . $this->templateName);
        $this->template->render();
    }
}
