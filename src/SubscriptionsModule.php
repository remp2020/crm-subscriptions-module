<?php

namespace Crm\SubscriptionsModule;

use Contributte\Translation\Translator;
use Crm\ApiModule\Models\Api\ApiRoutersContainerInterface;
use Crm\ApiModule\Models\Authorization\BearerTokenAuthorization;
use Crm\ApiModule\Models\Router\ApiIdentifier;
use Crm\ApiModule\Models\Router\ApiRoute;
use Crm\ApplicationModule\Application\CommandsContainerInterface;
use Crm\ApplicationModule\Application\Managers\SeederManager;
use Crm\ApplicationModule\CrmModule;
use Crm\ApplicationModule\Models\Access\AccessManager;
use Crm\ApplicationModule\Models\Criteria\CriteriaStorage;
use Crm\ApplicationModule\Models\Criteria\ScenariosCriteriaStorage;
use Crm\ApplicationModule\Models\DataProvider\DataProviderManager;
use Crm\ApplicationModule\Models\Event\EventsStorage;
use Crm\ApplicationModule\Models\Event\LazyEventEmitter;
use Crm\ApplicationModule\Models\Menu\MenuContainerInterface;
use Crm\ApplicationModule\Models\Menu\MenuItem;
use Crm\ApplicationModule\Models\User\UserDataRegistrator;
use Crm\ApplicationModule\Models\Widget\LazyWidgetManagerInterface;
use Crm\SubscriptionsModule\Access\SubscriptionAccessProvider;
use Crm\SubscriptionsModule\Api\v1\CreateSubscriptionHandler;
use Crm\SubscriptionsModule\Api\v1\ListContentAccessHandler;
use Crm\SubscriptionsModule\Api\v1\UpdateSubscriptionHandler;
use Crm\SubscriptionsModule\Api\v1\UsersSubscriptionsHandler;
use Crm\SubscriptionsModule\Commands\ChangeSubscriptionsStateCommand;
use Crm\SubscriptionsModule\Components\ActualSubscribersRegistrationSourceStatsWidget\ActualSubscribersRegistrationSourceStatsWidget;
use Crm\SubscriptionsModule\Components\ActualSubscribersStatWidget\ActualSubscribersStatWidget;
use Crm\SubscriptionsModule\Components\ActualUserSubscriptions\ActualUserSubscriptions;
use Crm\SubscriptionsModule\Components\EndingSubscriptionsWidget\EndingSubscriptionsWidget;
use Crm\SubscriptionsModule\Components\MonthSubscriptionsSmallBarGraphWidget\MonthSubscriptionsSmallBarGraphWidget;
use Crm\SubscriptionsModule\Components\MonthSubscriptionsStatWidget\MonthSubscriptionsStatWidget;
use Crm\SubscriptionsModule\Components\MonthToDateSubscriptionsStatWidget\MonthToDateSubscriptionsStatWidget;
use Crm\SubscriptionsModule\Components\PrintSubscribersWithoutPrintAddressWidget\PrintSubscribersWithoutPrintAddressWidget;
use Crm\SubscriptionsModule\Components\RenewedSubscriptionsEndingWithinPeriodWidget\RenewedSubscriptionsEndingWithinPeriodWidget;
use Crm\SubscriptionsModule\Components\StopSubscriptionWidget\StopSubscriptionWidget;
use Crm\SubscriptionsModule\Components\SubscriptionButton\SubscriptionButton;
use Crm\SubscriptionsModule\Components\SubscriptionTransferWidget\SubscriptionTransferWidget;
use Crm\SubscriptionsModule\Components\SubscriptionsEndingWithinPeriodWidget\SubscriptionsEndingWithinPeriodWidget;
use Crm\SubscriptionsModule\Components\TodaySubscriptionsStatWidget\TodaySubscriptionsStatWidget;
use Crm\SubscriptionsModule\Components\TotalSubscriptionsStatWidget\TotalSubscriptionsStatWidget;
use Crm\SubscriptionsModule\Components\UserSubscriptionAddressWidget\UserSubscriptionAddressWidget;
use Crm\SubscriptionsModule\Components\UserSubscriptionInfoWidget\UserSubscriptionInfoWidget;
use Crm\SubscriptionsModule\Components\UserSubscriptionsListing\UserSubscriptionsListing;
use Crm\SubscriptionsModule\Components\UsersAbusiveAdditionalWidget\UsersAbusiveAdditionalWidget;
use Crm\SubscriptionsModule\DataProviders\CanDeleteAddressDataProvider;
use Crm\SubscriptionsModule\DataProviders\FilterAbusiveUserFormDataProvider;
use Crm\SubscriptionsModule\DataProviders\FilterUserActionLogsFormDataProvider;
use Crm\SubscriptionsModule\DataProviders\FilterUserActionLogsSelectionDataProvider;
use Crm\SubscriptionsModule\DataProviders\FilterUsersFormDataProvider;
use Crm\SubscriptionsModule\DataProviders\SubscriptionTransferDataProvider;
use Crm\SubscriptionsModule\DataProviders\SubscriptionsClaimUserDataProvider;
use Crm\SubscriptionsModule\DataProviders\SubscriptionsUserDataProvider;
use Crm\SubscriptionsModule\Events\AddressRemovedHandler;
use Crm\SubscriptionsModule\Events\NewSubscriptionEvent;
use Crm\SubscriptionsModule\Events\SubscriptionEndsEvent;
use Crm\SubscriptionsModule\Events\SubscriptionPreUpdateEvent;
use Crm\SubscriptionsModule\Events\SubscriptionShortenedEvent;
use Crm\SubscriptionsModule\Events\SubscriptionShortenedHandler;
use Crm\SubscriptionsModule\Events\SubscriptionStartsEvent;
use Crm\SubscriptionsModule\Events\SubscriptionUpdatedEvent;
use Crm\SubscriptionsModule\Hermes\GenerateSubscriptionHandler;
use Crm\SubscriptionsModule\Repositories\SubscriptionsRepository;
use Crm\SubscriptionsModule\Scenarios\ContentAccessCriteria;
use Crm\SubscriptionsModule\Scenarios\FirstSubscriptionInPeriodCriteria;
use Crm\SubscriptionsModule\Scenarios\HasDisabledNotificationsCriteria;
use Crm\SubscriptionsModule\Scenarios\HasLaterEndingSubscriptionCriteria;
use Crm\SubscriptionsModule\Scenarios\IsConsecutiveSubscriptionCriteria;
use Crm\SubscriptionsModule\Scenarios\IsExpiredByAdminCriteria;
use Crm\SubscriptionsModule\Scenarios\IsRecurrentCriteria;
use Crm\SubscriptionsModule\Scenarios\SubscriptionScenarioConditionModel;
use Crm\SubscriptionsModule\Scenarios\SubscriptionTypeCriteria;
use Crm\SubscriptionsModule\Scenarios\SubscriptionTypeIsDefaultCriteria;
use Crm\SubscriptionsModule\Scenarios\SubscriptionTypeLengthCriteria;
use Crm\SubscriptionsModule\Scenarios\TypeCriteria;
use Crm\SubscriptionsModule\Seeders\ConfigSeeder;
use Crm\SubscriptionsModule\Seeders\ContentAccessSeeder;
use Crm\SubscriptionsModule\Seeders\MeasurementsSeeder;
use Crm\SubscriptionsModule\Seeders\SubscriptionExtensionMethodsSeeder;
use Crm\SubscriptionsModule\Seeders\SubscriptionLengthMethodSeeder;
use Crm\SubscriptionsModule\Seeders\SubscriptionTypeNamesSeeder;
use Crm\SubscriptionsModule\Segment\ActiveSubscriptionCriteria;
use Crm\SubscriptionsModule\Segment\InactiveSubscriptionCriteria;
use Crm\SubscriptionsModule\Segment\UserActiveSubscriptionCriteria;
use Crm\UsersModule\Events\AddressRemovedEvent;
use Crm\UsersModule\Events\RefreshUserDataTokenHandler;
use Crm\UsersModule\Models\Auth\UserTokenAuthorization;
use Nette\Application\Routers\RouteList;
use Nette\DI\Container;
use Symfony\Component\Console\Output\OutputInterface;
use Tomaj\Hermes\Dispatcher;

class SubscriptionsModule extends CrmModule
{
    private $subscriptionsRepository;

    public function __construct(Container $container, Translator $translator, SubscriptionsRepository $subscriptionsRepository)
    {
        parent::__construct($container, $translator);
        $this->subscriptionsRepository = $subscriptionsRepository;
    }

    public function registerAdminMenuItems(MenuContainerInterface $menuContainer)
    {
        $mainMenu = new MenuItem(
            $this->translator->translate('subscriptions.menu.subscriptions'),
            '#subscriptions',
            'fa fa-shopping-cart',
            500
        );

        $menuItem1 = new MenuItem(
            $this->translator->translate('subscriptions.menu.subscription_types'),
            ':Subscriptions:SubscriptionTypesAdmin:default',
            'fa fa-magic',
            600
        );

        $menuItem2 = new MenuItem(
            $this->translator->translate('subscriptions.menu.subscriptions_generator'),
            ':Subscriptions:SubscriptionsGenerator:default',
            'fa fa-copy',
            700
        );

        $mainMenu->addChild($menuItem1);
        $mainMenu->addChild($menuItem2);

        $menuContainer->attachMenuItem($mainMenu);

        // dashboard menu item

        $menuItem = new MenuItem(
            $this->translator->translate('subscriptions.menu.stats'),
            ':Subscriptions:Dashboard:default',
            'fa fa-shopping-cart',
            400
        );
        $menuContainer->attachMenuItemToForeignModule('#dashboard', $mainMenu, $menuItem);

        $menuItem = new MenuItem(
            $this->translator->translate('subscriptions.menu.endings'),
            ':Subscriptions:Dashboard:endings',
            'fa fa-frown fa-fw',
            500
        );
        $menuContainer->attachMenuItemToForeignModule('#dashboard', $mainMenu, $menuItem);
    }

    public function registerFrontendMenuItems(MenuContainerInterface $menuContainer)
    {
        $menuItem = new MenuItem($this->translator->translate('subscriptions.menu.subscriptions'), ':Subscriptions:Subscriptions:my', '', 5);
        $menuContainer->attachMenuItem($menuItem);
    }

    public function registerLazyWidgets(LazyWidgetManagerInterface $widgetManager)
    {
        $widgetManager->registerWidget(
            'admin.user.detail.bottom',
            UserSubscriptionsListing::class,
            100
        );
        $widgetManager->registerWidget(
            'admin.payments.listing.action.menu',
            SubscriptionButton::class,
            6000
        );
        $widgetManager->registerWidget(
            'admin.user.detail.box',
            ActualUserSubscriptions::class,
            300
        );
        $widgetManager->registerWidget(
            'dashboard.singlestat.totals',
            TotalSubscriptionsStatWidget::class,
            600
        );
        $widgetManager->registerWidget(
            'dashboard.singlestat.actuals.subscribers',
            ActualSubscribersStatWidget::class,
            700
        );
        $widgetManager->registerWidget(
            'dashboard.stats.actuals.subscribers.source',
            ActualSubscribersRegistrationSourceStatsWidget::class,
            700
        );
        $widgetManager->registerWidget(
            'dashboard.singlestat.today',
            TodaySubscriptionsStatWidget::class,
            500
        );
        $widgetManager->registerWidget(
            'dashboard.singlestat.month',
            MonthSubscriptionsStatWidget::class,
            600
        );
        $widgetManager->registerWidget(
            'dashboard.singlestat.mtd',
            MonthToDateSubscriptionsStatWidget::class,
            600
        );
        $widgetManager->registerWidget(
            'dashboard.bottom',
            EndingSubscriptionsWidget::class,
            100
        );
        $widgetManager->registerWidget(
            'subscriptions.endinglist',
            SubscriptionsEndingWithinPeriodWidget::class,
            500
        );
        $widgetManager->registerWidget(
            'subscriptions.endinglist',
            RenewedSubscriptionsEndingWithinPeriodWidget::class,
            600
        );
        $widgetManager->registerWidget(
            'admin.users.header',
            MonthSubscriptionsSmallBarGraphWidget::class,
            600
        );
        $widgetManager->registerWidget(
            'admin.user.list.emailcolumn',
            UserSubscriptionInfoWidget::class,
            600
        );
        $widgetManager->registerWidget(
            'admin.payments.top',
            PrintSubscribersWithoutPrintAddressWidget::class,
            2000
        );
        $widgetManager->registerWidget(
            'admin.user.abusive.additional',
            UsersAbusiveAdditionalWidget::class
        );
        $widgetManager->registerWidget(
            'subscriptions.admin.user_subscriptions_listing.action.menu',
            StopSubscriptionWidget::class,
            10 // set priority to ensure widget renders first in action menu, adds menu items header
        );

        $widgetManager->registerWidget(
            'subscriptions.admin.user_subscriptions_listing.action.menu',
            SubscriptionTransferWidget::class,
        );

        $widgetManager->registerWidget(
            'subscriptions.admin.user_subscriptions_listing.subscription',
            UserSubscriptionAddressWidget::class,
            1, // set priority to ensure the widget is rendered first
        );
    }

    public function registerLazyEventHandlers(LazyEventEmitter $emitter)
    {
        $emitter->addListener(
            NewSubscriptionEvent::class,
            RefreshUserDataTokenHandler::class,
        );
        $emitter->addListener(
            SubscriptionUpdatedEvent::class,
            RefreshUserDataTokenHandler::class,
        );
        $emitter->addListener(
            AddressRemovedEvent::class,
            AddressRemovedHandler::class,
        );
        $emitter->addListener(
            SubscriptionShortenedEvent::class,
            SubscriptionShortenedHandler::class,
        );
    }

    public function registerHermesHandlers(Dispatcher $dispatcher)
    {
        $dispatcher->registerHandler(
            'generate-subscription',
            $this->getInstance(GenerateSubscriptionHandler::class)
        );
    }

    public function registerCommands(CommandsContainerInterface $commandsContainer)
    {
        $commandsContainer->registerCommand($this->getInstance(ChangeSubscriptionsStateCommand::class));
    }

    public function registerApiCalls(ApiRoutersContainerInterface $apiRoutersContainer)
    {
        $apiRoutersContainer->attachRouter(
            new ApiRoute(
                new ApiIdentifier('1', 'users', 'subscriptions'),
                UsersSubscriptionsHandler::class,
                UserTokenAuthorization::class
            )
        );

        $apiRoutersContainer->attachRouter(
            new ApiRoute(
                new ApiIdentifier('1', 'subscriptions', 'create'),
                CreateSubscriptionHandler::class,
                BearerTokenAuthorization::class
            )
        );

        $apiRoutersContainer->attachRouter(
            new ApiRoute(
                new ApiIdentifier('1', 'content-access', 'list'),
                ListContentAccessHandler::class,
                BearerTokenAuthorization::class
            )
        );

        $apiRoutersContainer->attachRouter(
            new ApiRoute(
                new ApiIdentifier('1', 'subscriptions', 'update'),
                UpdateSubscriptionHandler::class,
                BearerTokenAuthorization::class
            )
        );
    }

    public function registerUserData(UserDataRegistrator $dataRegistrator)
    {
        $dataRegistrator->addUserDataProvider($this->getInstance(SubscriptionsUserDataProvider::class));
    }

    public function registerScenariosCriteria(ScenariosCriteriaStorage $scenariosCriteriaStorage)
    {
        $scenariosCriteriaStorage->register('subscription', 'type', $this->getInstance(TypeCriteria::class));
        $scenariosCriteriaStorage->register('subscription', 'subscription_type', $this->getInstance(SubscriptionTypeCriteria::class));
        $scenariosCriteriaStorage->register('subscription', SubscriptionTypeIsDefaultCriteria::KEY, $this->getInstance(SubscriptionTypeIsDefaultCriteria::class));
        $scenariosCriteriaStorage->register('subscription', 'content_access', $this->getInstance(ContentAccessCriteria::class));
        $scenariosCriteriaStorage->register('subscription', 'is_recurrent', $this->getInstance(IsRecurrentCriteria::class));
        $scenariosCriteriaStorage->register('subscription', HasDisabledNotificationsCriteria::KEY, $this->getInstance(HasDisabledNotificationsCriteria::class));
        $scenariosCriteriaStorage->register('subscription', SubscriptionTypeLengthCriteria::KEY, $this->getInstance(SubscriptionTypeLengthCriteria::class));
        $scenariosCriteriaStorage->register('subscription', HasLaterEndingSubscriptionCriteria::KEY, $this->getInstance(HasLaterEndingSubscriptionCriteria::class));
        $scenariosCriteriaStorage->register('subscription', IsExpiredByAdminCriteria::KEY, $this->getInstance(IsExpiredByAdminCriteria::class));
        $scenariosCriteriaStorage->register('subscription', FirstSubscriptionInPeriodCriteria::KEY, $this->getInstance(FirstSubscriptionInPeriodCriteria::class));
        $scenariosCriteriaStorage->register('subscription', IsConsecutiveSubscriptionCriteria::KEY, $this->getInstance(IsConsecutiveSubscriptionCriteria::class));

        $scenariosCriteriaStorage->registerConditionModel(
            'subscription',
            $this->getInstance(SubscriptionScenarioConditionModel::class)
        );
    }

    public function registerSegmentCriteria(CriteriaStorage $criteriaStorage)
    {
        $criteriaStorage->register('users', 'users_active_subscription', $this->getInstance(UserActiveSubscriptionCriteria::class));
        $criteriaStorage->register('subscriptions', 'subscriptions_active_subscription', $this->getInstance(ActiveSubscriptionCriteria::class));

        $criteriaStorage->register('users', 'users_inactive_subscription', $this->getInstance(InactiveSubscriptionCriteria::class));

        $criteriaStorage->setDefaultFields('subscriptions', ['id']);
        $criteriaStorage->setFields('subscriptions', [
            'start_time',
            'end_time',
            'is_recurrent',
            'type',
            'length',
            'created_at',
            'note',
        ]);
    }

    public function registerRoutes(RouteList $router)
    {
        $router->addRoute('subscriptions/[funnel/<funnel>]', 'Subscriptions:Subscriptions:new');
    }

    public function registerSeeders(SeederManager $seederManager)
    {
        $seederManager->addSeeder($this->getInstance(ConfigSeeder::class));
        $seederManager->addSeeder($this->getInstance(ContentAccessSeeder::class));
        $seederManager->addSeeder($this->getInstance(SubscriptionExtensionMethodsSeeder::class));
        $seederManager->addSeeder($this->getInstance(SubscriptionLengthMethodSeeder::class));
        $seederManager->addSeeder($this->getInstance(SubscriptionTypeNamesSeeder::class));
        $seederManager->addSeeder($this->getInstance(MeasurementsSeeder::class));
    }

    public function registerAccessProvider(AccessManager $accessManager)
    {
        $accessManager->addAccessProvider($this->getInstance(SubscriptionAccessProvider::class));
    }

    public function registerDataProviders(DataProviderManager $dataProviderManager)
    {
        $dataProviderManager->registerDataProvider(
            'users.dataprovider.users_filter_form',
            $this->getInstance(FilterUsersFormDataProvider::class)
        );
        $dataProviderManager->registerDataProvider(
            'users.dataprovider.filter_user_actions_log_selection',
            $this->getInstance(FilterUserActionLogsSelectionDataProvider::class)
        );
        $dataProviderManager->registerDataProvider(
            'users.dataprovider.filter_user_actions_log_form',
            $this->getInstance(FilterUserActionLogsFormDataProvider::class)
        );
        $dataProviderManager->registerDataProvider(
            'users.dataprovider.address.can_delete',
            $this->getInstance(CanDeleteAddressDataProvider::class)
        );
        $dataProviderManager->registerDataProvider(
            'users.dataprovider.filter_abusive_user_form',
            $this->getInstance(FilterAbusiveUserFormDataProvider::class)
        );
        $dataProviderManager->registerDataProvider(
            'users.dataprovider.claim_unclaimed_user',
            $this->getInstance(SubscriptionsClaimUserDataProvider::class)
        );
        $dataProviderManager->registerDataProvider(
            'subscriptions.dataprovider.transfer',
            $this->getInstance(SubscriptionTransferDataProvider::class),
            priority: 1000, // priority is set due to manipulation with address and unlinking it from subscription
        );
    }

    public function registerEvents(EventsStorage $eventsStorage)
    {
        $eventsStorage->register('new_subscription', NewSubscriptionEvent::class, true);
        $eventsStorage->register('subscription_pre_update', SubscriptionPreUpdateEvent::class);
        $eventsStorage->register('subscription_updated', SubscriptionUpdatedEvent::class);
        $eventsStorage->register('subscription_starts', SubscriptionStartsEvent::class, true);
        $eventsStorage->register('subscription_ends', SubscriptionEndsEvent::class, true);
    }

    public function cache(OutputInterface $output, array $tags = [])
    {
        if (in_array('precalc', $tags, true)) {
            $output->writeln('  * Refreshing <info>subscriptions stats</info> cache');

            $this->subscriptionsRepository->totalCount(true, true);
            $this->subscriptionsRepository->currentSubscribersCount(true, true);
        }
    }
}
