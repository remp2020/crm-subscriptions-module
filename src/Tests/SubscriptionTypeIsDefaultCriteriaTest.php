<?php

namespace Crm\SubscriptionsModule\Tests;

use Crm\ApplicationModule\Tests\DatabaseTestCase;
use Crm\SubscriptionsModule\Models\Builder\SubscriptionTypeBuilder;
use Crm\SubscriptionsModule\Repositories\SubscriptionTypesRepository;
use Crm\SubscriptionsModule\Repositories\SubscriptionsRepository;
use Crm\SubscriptionsModule\Scenarios\SubscriptionTypeIsDefaultCriteria;
use Crm\SubscriptionsModule\Seeders\SubscriptionExtensionMethodsSeeder;
use Crm\SubscriptionsModule\Seeders\SubscriptionLengthMethodSeeder;
use Crm\SubscriptionsModule\Seeders\SubscriptionTypeNamesSeeder;
use Crm\UsersModule\Models\Auth\UserManager;
use Crm\UsersModule\Repositories\UsersRepository;

class SubscriptionTypeIsDefaultCriteriaTest extends DatabaseTestCase
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
            SubscriptionTypesRepository::class,
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

    public function testSubTypeDefaultAndDefaultRequired()
    {
        [$subscriptionSelection, $subscriptionRow] = $this->prepareData(1);

        $criteria = $this->inject(SubscriptionTypeIsDefaultCriteria::class);

        $criteria->addConditions($subscriptionSelection, [
            SubscriptionTypeIsDefaultCriteria::KEY => (object)['selection' => 1],
        ], $subscriptionRow);

        $this->assertNotNull($subscriptionSelection->fetch());
    }

    public function testSubTypeDefaultAndNotDefaultRequired()
    {
        [$subscriptionSelection, $subscriptionRow] = $this->prepareData(1);

        $criteria = $this->inject(SubscriptionTypeIsDefaultCriteria::class);

        $criteria->addConditions($subscriptionSelection, [
            SubscriptionTypeIsDefaultCriteria::KEY => (object)['selection' => 0],
        ], $subscriptionRow);

        $this->assertNull($subscriptionSelection->fetch());
    }

    public function testSubTypeNotDefaultAndDefaultRequired()
    {
        [$subscriptionSelection, $subscriptionRow] = $this->prepareData(0);

        $criteria = $this->inject(SubscriptionTypeIsDefaultCriteria::class);

        $criteria->addConditions($subscriptionSelection, [
            SubscriptionTypeIsDefaultCriteria::KEY => (object)['selection' => 1],
        ], $subscriptionRow);

        $this->assertNull($subscriptionSelection->fetch());
    }

    public function testSubTypeNotDefaultAndNotDefaultRequired()
    {
        [$subscriptionSelection, $subscriptionRow] = $this->prepareData(1);

        $criteria = $this->inject(SubscriptionTypeIsDefaultCriteria::class);

        $criteria->addConditions($subscriptionSelection, [
            SubscriptionTypeIsDefaultCriteria::KEY => (object)['selection' => 1],
        ], $subscriptionRow);

        $this->assertNotNull($subscriptionSelection->fetch());
    }

    private function prepareData($defaultSubType)
    {
        /** @var SubscriptionTypeBuilder $subscriptionTypeBuilder */
        $subscriptionTypeBuilder = $this->inject(SubscriptionTypeBuilder::class);

        $subscriptionTypeRow = $subscriptionTypeBuilder
            ->createNew()
            ->setNameAndUserLabel('Test')
            ->setActive(1)
            ->setPrice(1)
            ->setLength(30)
            ->setDefault($defaultSubType)
            ->save();

        /** @var SubscriptionsRepository $subscriptionRepository */
        $subscriptionRepository = $this->getRepository(SubscriptionsRepository::class);

        /** @var UserManager $userManager */
        $userManager = $this->inject(UserManager::class);
        $userRow = $userManager->addNewUser('test_sub_type_def@example.com');

        $subscriptionRow = $subscriptionRepository->add(
            $subscriptionTypeRow,
            false,
            false,
            $userRow,
        );

        $subscriptionSelection = $this->subscriptionsRepository->getTable()
            ->where(['subscriptions.id' => $subscriptionRow->id]);

        return [$subscriptionSelection, $subscriptionRow];
    }
}
