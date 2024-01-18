<?php

namespace Crm\SubscriptionsModule\Tests;

use Crm\ApplicationModule\Tests\DatabaseTestCase;
use Crm\SubscriptionsModule\Models\Builder\SubscriptionTypeBuilder;
use Crm\SubscriptionsModule\Repositories\ContentAccessRepository;
use Crm\SubscriptionsModule\Repositories\SubscriptionTypeContentAccessRepository;
use Crm\SubscriptionsModule\Repositories\SubscriptionTypeNamesRepository;
use Crm\SubscriptionsModule\Repositories\SubscriptionTypesRepository;
use Crm\SubscriptionsModule\Repositories\SubscriptionsRepository;
use Crm\SubscriptionsModule\Scenarios\ContentAccessCriteria;
use Crm\SubscriptionsModule\Seeders\ContentAccessSeeder;
use Crm\SubscriptionsModule\Seeders\SubscriptionExtensionMethodsSeeder;
use Crm\SubscriptionsModule\Seeders\SubscriptionLengthMethodSeeder;
use Crm\SubscriptionsModule\Seeders\SubscriptionTypeNamesSeeder;
use Crm\UsersModule\Auth\UserManager;
use Crm\UsersModule\Repository\UsersRepository;
use PHPUnit\Framework\Attributes\DataProvider;

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
            SubscriptionExtensionMethodsSeeder::class,
            SubscriptionLengthMethodSeeder::class,
            SubscriptionTypeNamesSeeder::class,
            ContentAccessSeeder::class,
        ];
    }

    public static function dataProviderForTestSubscriptionHasContentAccessCriteria(): array
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

    #[DataProvider('dataProviderForTestSubscriptionHasContentAccessCriteria')]
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
