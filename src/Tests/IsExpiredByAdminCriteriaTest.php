<?php

namespace Crm\SubscriptionsModule\Tests;

use Crm\ApplicationModule\Tests\DatabaseTestCase;
use Crm\SubscriptionsModule\Builder\SubscriptionTypeBuilder;
use Crm\SubscriptionsModule\Repository\SubscriptionMetaRepository;
use Crm\SubscriptionsModule\Repository\SubscriptionTypesRepository;
use Crm\SubscriptionsModule\Repository\SubscriptionsRepository;
use Crm\SubscriptionsModule\Scenarios\IsExpiredByAdminCriteria;
use Crm\SubscriptionsModule\Seeders\SubscriptionExtensionMethodsSeeder;
use Crm\SubscriptionsModule\Seeders\SubscriptionLengthMethodSeeder;
use Crm\SubscriptionsModule\Seeders\SubscriptionTypeNamesSeeder;
use Crm\SubscriptionsModule\Subscription\StopSubscriptionHandler;
use Crm\UsersModule\Auth\UserManager;
use Crm\UsersModule\Repository\UsersRepository;

class IsExpiredByAdminCriteriaTest extends DatabaseTestCase
{
    /** @var SubscriptionsRepository */
    private $subscriptionRepository;

    /** @var SubscriptionMetaRepository */
    private $subscriptionMetaRepository;

    public function setUp(): void
    {
        parent::setUp();

        $this->subscriptionRepository = $this->getRepository(SubscriptionsRepository::class);
        $this->subscriptionMetaRepository = $this->getRepository(SubscriptionMetaRepository::class);
    }

    protected function requiredRepositories(): array
    {
        return [
            SubscriptionsRepository::class,
            SubscriptionTypesRepository::class,
            SubscriptionMetaRepository::class,
            UsersRepository::class,
        ];
    }

    protected function requiredSeeders(): array
    {
        return [
            SubscriptionExtensionMethodsSeeder::class,
            SubscriptionLengthMethodSeeder::class,
            SubscriptionTypeNamesSeeder::class,
        ];
    }

    public function testIsExpiredByAdminRequired(): void
    {
        [$subscriptionSelection, $subscriptionRow] = $this->prepareData();

        /** @var IsExpiredByAdminCriteria $criteria */
        $criteria = $this->inject(IsExpiredByAdminCriteria::class);

        $criteria->addConditions($subscriptionSelection, [
                IsExpiredByAdminCriteria::KEY => (object)['selection' => 1]
            ], $subscriptionRow);
        $this->assertFalse($subscriptionSelection->fetch());

        $this->subscriptionMetaRepository->setMeta($subscriptionRow, StopSubscriptionHandler::META_KEY_EXPIRED_BY_ADMIN, false);

        $criteria->addConditions($subscriptionSelection, [
            IsExpiredByAdminCriteria::KEY => (object)['selection' => 1]
        ], $subscriptionRow);
        $this->assertFalse($subscriptionSelection->fetch());

        $this->subscriptionMetaRepository->setMeta($subscriptionRow, StopSubscriptionHandler::META_KEY_EXPIRED_BY_ADMIN, true);

        $criteria->addConditions($subscriptionSelection, [
                IsExpiredByAdminCriteria::KEY => (object)['selection' => 1]
            ], $subscriptionRow);
        $this->assertNotFalse($subscriptionSelection->fetch());
    }

    public function testIsExpiredByAdminNotRequired(): void
    {
        [$subscriptionSelection, $subscriptionRow] = $this->prepareData();

        /** @var IsExpiredByAdminCriteria $criteria */
        $criteria = $this->inject(IsExpiredByAdminCriteria::class);
        $criteria->addConditions($subscriptionSelection, [
                IsExpiredByAdminCriteria::KEY => (object)['selection' => 0]
            ], $subscriptionRow);
        $this->assertNotFalse($subscriptionSelection->fetch());

        $this->subscriptionMetaRepository->setMeta($subscriptionRow, StopSubscriptionHandler::META_KEY_EXPIRED_BY_ADMIN, false);

        $criteria->addConditions($subscriptionSelection, [
                IsExpiredByAdminCriteria::KEY => (object)['selection' => 0]
            ], $subscriptionRow);
        $this->assertNotFalse($subscriptionSelection->fetch());

        $this->subscriptionMetaRepository->setMeta($subscriptionRow, StopSubscriptionHandler::META_KEY_EXPIRED_BY_ADMIN, true);
        $criteria->addConditions($subscriptionSelection, [
                IsExpiredByAdminCriteria::KEY => (object)['selection' => 0]
            ], $subscriptionRow);
        $this->assertFalse($subscriptionSelection->fetch());
    }

    private function prepareData(): array
    {
        /** @var SubscriptionTypeBuilder $subscriptionTypeBuilder */
        $subscriptionTypeBuilder = $this->inject(SubscriptionTypeBuilder::class);

        $subscriptionTypeRow = $subscriptionTypeBuilder
            ->createNew()
            ->setNameAndUserLabel('Test')
            ->setActive(1)
            ->setPrice(1)
            ->setLength(30)
            ->save();
        /** @var UserManager $userManager */
        $userManager = $this->inject(UserManager::class);
        $userRow = $userManager->addNewUser('test@example.com');

        $subscriptionRow = $this->subscriptionRepository->add(
            $subscriptionTypeRow,
            false,
            false,
            $userRow
        );

        $subscriptionSelection = $this->subscriptionRepository->getTable()
            ->where(['subscriptions.id' => $subscriptionRow->id]);

        return [$subscriptionSelection, $subscriptionRow];
    }
}