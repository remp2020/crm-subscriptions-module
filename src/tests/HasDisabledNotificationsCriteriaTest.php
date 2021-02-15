<?php

namespace Crm\SubscriptionsModule\Tests;

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
        [$userSelection, $userRow] = $this->prepareData(true);

        $hasDisabledNotification = new HasDisabledNotificationsCriteria();
        $values = (object)['selection' => true];
        $hasDisabledNotification->addConditions($userSelection, [HasDisabledNotificationsCriteria::KEY => $values], $userRow);

        $this->assertNotFalse($userSelection->fetch());
    }

    public function testHasDisabledNotificationsWithEnabledNotifications(): void
    {
        [$userSelection, $userRow] = $this->prepareData(false);

        $hasDisabledNotification = new HasDisabledNotificationsCriteria();
        $values = (object)['selection' => true];
        $hasDisabledNotification->addConditions($userSelection, [HasDisabledNotificationsCriteria::KEY => $values], $userRow);

        $this->assertFalse($userSelection->fetch());
    }

    public function testHasEnabledNotificationsWithDisabledNotifications(): void
    {
        [$userSelection, $userRow] = $this->prepareData(true);

        $hasDisabledNotification = new HasDisabledNotificationsCriteria();
        $values = (object)['selection' => false];
        $hasDisabledNotification->addConditions($userSelection, [HasDisabledNotificationsCriteria::KEY => $values], $userRow);

        $this->assertFalse($userSelection->fetch());
    }

    public function testHasEnabledNotificationsWithEnabledNotifications(): void
    {
        [$userSeletion, $userRow] = $this->prepareData(false);

        $hasDisabledNotification = new HasDisabledNotificationsCriteria();
        $values = (object)['selection' => false];
        $hasDisabledNotification->addConditions($userSeletion, [HasDisabledNotificationsCriteria::KEY => $values], $userRow);

        $this->assertNotFalse($userSeletion->fetch());
    }

    private function prepareData(bool $disabledNotifications): array
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

        $selection = $this->subscriptionsRepository->getTable()
            ->where(['subscriptions.id' => $subscriptionRow->id]);

        return [$selection, $subscriptionRow];
    }
}
