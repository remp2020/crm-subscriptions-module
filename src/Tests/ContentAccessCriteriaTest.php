<?php

namespace Crm\SubscriptionsModule\Tests;

use Crm\ApplicationModule\Tests\DatabaseTestCase;
use Crm\SubscriptionsModule\Builder\SubscriptionTypeBuilder;
use Crm\SubscriptionsModule\Repository\ContentAccessRepository;
use Crm\SubscriptionsModule\Repository\SubscriptionTypeContentAccessRepository;
use Crm\SubscriptionsModule\Repository\SubscriptionTypeNamesRepository;
use Crm\SubscriptionsModule\Repository\SubscriptionTypesRepository;
use Crm\SubscriptionsModule\Repository\SubscriptionsRepository;
use Crm\SubscriptionsModule\Scenarios\ContentAccessCriteria;
use Crm\SubscriptionsModule\Seeders\ContentAccessSeeder;
use Crm\SubscriptionsModule\Seeders\SubscriptionTypeNamesSeeder;
use Crm\UsersModule\Auth\UserManager;
use Crm\UsersModule\Repository\UsersRepository;

class ContentAccessCriteriaTest extends DatabaseTestCase
{
    public function requiredRepositories(): array
    {
        return [
            SubscriptionTypeNamesRepository::class,
            SubscriptionsRepository::class,
            SubscriptionTypesRepository::class,
            UsersRepository::class,
            ContentAccessRepository::class,
            SubscriptionTypeContentAccessRepository::class,
        ];
    }

    public function requiredSeeders(): array
    {
        return [
            SubscriptionTypeNamesSeeder::class,
            ContentAccessSeeder::class,
        ];
    }

    public function dataProviderForTestSubscriptionHasContentAccessCriteria(): array
    {
        return [
            'MatchingContentAccess_ShouldReturnTrue' => [
                "hasContentAccess" => ['web', 'print'],
                "testedContentAccess" => [
                    ['web', 'club'],
                ],
                "expectedResult" => true,
            ],
            'DifferentContentAccess_ShouldReturnFalse' => [
                "hasContentAccess" => ['web', 'print'],
                "testedContentAccess" => [
                    ['mobile'],
                ],
                "expectedResult" => false,
            ],
            'EmptyTestedContentAccess_ShouldReturnTrue' => [
                "hasContentAccess" => ['web', 'print'],
                "testedContentAccess" => [
                    [],
                ],
                "expectedResult" => true,
            ],
            'ChainedMatchingContentAccess_ShouldReturnTrue' => [
                "hasContentAccess" => ['web', 'print'],
                "testedContentAccess" => [
                    ['web', 'mobile'],
                    ['print'],
                ],
                "expectedResult" => true,
            ],
            'ChainedPartiallyDifferentContentAccess_ShouldReturnFalse' => [
                "hasContentAccess" => ['web', 'print'],
                "testedContentAccess" => [
                    ['mobile'],
                    ['print'],
                ],
                "expectedResult" => false,
            ],
        ];
    }

    /**
     * @dataProvider dataProviderForTestSubscriptionHasContentAccessCriteria
     */
    public function testSubscriptionHasContentAccessCriteria($hasContentAccess, $testedContentAccess, $expectedResult)
    {
        [$subscriptionSelection, $subscriptionRow] = $this->prepareData($hasContentAccess);

        /** @var ContentAccessCriteria $criteria */
        $criteria = $this->inject(ContentAccessCriteria::class);

        foreach ($testedContentAccess as $contentAccess) {
            $values = (object)['selection' => $contentAccess];
            $criteria->addConditions($subscriptionSelection, [ContentAccessCriteria::KEY => $values], $subscriptionRow);
        }

        if ($expectedResult) {
            $this->assertNotNull($subscriptionSelection->fetch());
        } else {
            $this->assertNull($subscriptionSelection->fetch());
        }
    }

    private function prepareData(array $hasContentAccess): array
    {
        /** @var SubscriptionTypeBuilder $subscriptionTypeBuilder */
        $subscriptionTypeBuilder = $this->inject(SubscriptionTypeBuilder::class);

        $subscriptionTypeRow = $subscriptionTypeBuilder
            ->createNew()
            ->setNameAndUserLabel('Test')
            ->setActive(1)
            ->setPrice(1)
            ->setLength(31)
            ->setContentAccessOption(...$hasContentAccess)
            ->save();

        /** @var SubscriptionsRepository $subscriptionsRepository */
        $subscriptionsRepository = $this->getRepository(SubscriptionsRepository::class);

        /** @var UserManager $userManager */
        $userManager = $this->inject(UserManager::class);
        $userRow = $userManager->addNewUser('user@example.com');

        $subscriptionRow = $subscriptionsRepository->add(
            $subscriptionTypeRow,
            false,
            false,
            $userRow,
            SubscriptionsRepository::TYPE_REGULAR,
        );

        $subscriptionSelection = $subscriptionsRepository->getTable()->where('subscriptions.id', $subscriptionRow->id);
        return [$subscriptionSelection, $subscriptionRow];
    }
}
