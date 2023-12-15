<?php

namespace Crm\SubscriptionsModule\Tests;

use Crm\ApplicationModule\Tests\DatabaseTestCase;
use Crm\SubscriptionsModule\Builder\SubscriptionTypeBuilder;
use Crm\SubscriptionsModule\Repository\SubscriptionsRepository;
use Crm\SubscriptionsModule\Scenarios\SubscriptionTypeLengthCriteria;
use Crm\SubscriptionsModule\Seeders\ContentAccessSeeder;
use Crm\SubscriptionsModule\Seeders\SubscriptionExtensionMethodsSeeder;
use Crm\SubscriptionsModule\Seeders\SubscriptionLengthMethodSeeder;
use Crm\SubscriptionsModule\Seeders\SubscriptionTypeNamesSeeder;
use Crm\UsersModule\Auth\UserManager;
use Crm\UsersModule\Repository\UsersRepository;
use PHPUnit\Framework\Attributes\DataProvider;

class SubscriptionTypeLengthCriteriaTest extends DatabaseTestCase
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

    #[DataProvider('dataProviderForTestCriteria')]
    public function testCriteria($subscriptionTypeLength, $criteriaOperator, $criteriaLength, $shouldFetch)
    {
        [$selection, $subscriptionRow] = $this->prepareData('user@example.com', $subscriptionTypeLength);
        $criteria = new SubscriptionTypeLengthCriteria();
        $criteria->addConditions($selection, [
            'subscription_type_length' => (object)['selection' => $criteriaLength, 'operator' => $criteriaOperator]
        ], $subscriptionRow);
        if ($shouldFetch) {
            $this->assertNotNull($selection->fetch());
        } else {
            $this->assertNull($selection->fetch());
        }
    }

    public static function dataProviderForTestCriteria(): array
    {
        return [
            [10, '=', 10, true],
            [10, '=', 11, false],
            [10, '>', 1, true],
            [10, '<', 1, false],
            [6, '>=', 5, true],
        ];
    }

    private function prepareData($userEmail, $lengthDays): array
    {
        /** @var SubscriptionTypeBuilder $subscriptionTypeBuilder */
        $subscriptionTypeBuilder = $this->inject(SubscriptionTypeBuilder::class);

        $subscriptionTypeRow = $subscriptionTypeBuilder
            ->createNew()
            ->setNameAndUserLabel('Test')
            ->setActive(1)
            ->setPrice(1)
            ->setLength($lengthDays)
            ->save();

        /** @var SubscriptionsRepository $subscriptionRepository */
        $subscriptionRepository = $this->getRepository(SubscriptionsRepository::class);

        /** @var UserManager $userManager */
        $userManager = $this->inject(UserManager::class);
        $userRow = $userManager->addNewUser($userEmail);

        $subscriptionRow = $subscriptionRepository->add(
            $subscriptionTypeRow,
            false,
            false,
            $userRow
        );

        $subscriptionSelection = $this->subscriptionsRepository->getTable()
            ->where(['subscriptions.id' => $subscriptionRow->id]);

        return [$subscriptionSelection, $subscriptionRow];
    }
}
