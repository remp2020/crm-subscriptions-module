<?php

namespace Crm\SubscriptionsModule\Tests;

use Crm\ApplicationModule\Selection;
use Crm\ApplicationModule\Tests\DatabaseTestCase;
use Crm\SubscriptionsModule\Builder\SubscriptionTypeBuilder;
use Crm\SubscriptionsModule\Repository\SubscriptionsRepository;
use Crm\SubscriptionsModule\Scenarios\HasDisabledNotificationsCriteria;
use Crm\SubscriptionsModule\Seeders\ContentAccessSeeder;
use Crm\SubscriptionsModule\Seeders\SubscriptionExtensionMethodsSeeder;
use Crm\SubscriptionsModule\Seeders\SubscriptionLengthMethodSeeder;
use Crm\SubscriptionsModule\Seeders\SubscriptionTypeNamesSeeder;
use Crm\UsersModule\Auth\UserManager;
use Crm\UsersModule\Repository\UsersRepository;

class HasDisabledNotificationsCriteriaTest extends DatabaseTestCase
{
    /** @var SubscriptionsRepository */
    private $subscriptionsRepository;

    public function setUp(): void
    {
        parent::setUp();

        $this->subscriptionsRepository = $this->getRepository(SubscriptionsRepository::class);
    }

    protected function requiredRepositories(): array
    {
        return [
            SubscriptionsRepository::class,
            UsersRepository::class,
        ];
    }

    protected function requiredSeeders(): array
    {
        return [
            ContentAccessSeeder::class,
            SubscriptionExtensionMethodsSeeder::class,
            SubscriptionLengthMethodSeeder::class,
            SubscriptionTypeNamesSeeder::class,
        ];
    }

    public function testHasDisabledNotificationsWithDisabledNotifications(): void
    {
        $selection = $this->prepareData(true);

        $hasDisabledNotification = new HasDisabledNotificationsCriteria();
        $hasDisabledNotification->addCondition($selection, HasDisabledNotificationsCriteria::KEY, (object)['selection' => true]);

        $this->assertNotFalse($selection->fetch());
    }

    public function testHasDisabledNotificationsWithEnabledNotifications(): void
    {
        $selection = $this->prepareData(false);

        $hasDisabledNotification = new HasDisabledNotificationsCriteria();
        $hasDisabledNotification->addCondition($selection, HasDisabledNotificationsCriteria::KEY, (object)['selection' => true]);

        $this->assertFalse($selection->fetch());
    }

    public function testHasEnabledNotificationsWithDisabledNotifications(): void
    {
        $selection = $this->prepareData(true);

        $hasDisabledNotification = new HasDisabledNotificationsCriteria();
        $hasDisabledNotification->addCondition($selection, HasDisabledNotificationsCriteria::KEY, (object)['selection' => false]);

        $this->assertFalse($selection->fetch());
    }

    public function testHasEnabledNotificationsWithEnabledNotifications(): void
    {
        $selection = $this->prepareData(false);

        $hasDisabledNotification = new HasDisabledNotificationsCriteria();
        $hasDisabledNotification->addCondition($selection, HasDisabledNotificationsCriteria::KEY, (object)['selection' => false]);

        $this->assertNotFalse($selection->fetch());
    }

    private function prepareData(bool $disabledNotifications): Selection
    {
        /** @var SubscriptionTypeBuilder $subscriptionTypeBuilder */
        $subscriptionTypeBuilder = $this->inject(SubscriptionTypeBuilder::class);

        $subscriptionTypeRow = $subscriptionTypeBuilder
            ->createNew()
            ->setNameAndUserLabel('Test')
            ->setActive(1)
            ->setPrice(1)
            ->setLength(31)
            ->setDisabledNotifications($disabledNotifications)
            ->save();

        /** @var SubscriptionsRepository $subscriptionRepository */
        $subscriptionRepository = $this->getRepository(SubscriptionsRepository::class);

        /** @var UserManager $userManager */
        $userManager = $this->inject(UserManager::class);
        $userRow = $userManager->addNewUser('test@test.sk');

        $subscriptionRow = $subscriptionRepository->add(
            $subscriptionTypeRow,
            false,
            false,
            $userRow
        );

        return $this->subscriptionsRepository->getTable()
            ->where(['subscriptions.id' => $subscriptionRow->id]);
    }
}
