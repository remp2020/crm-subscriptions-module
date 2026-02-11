<?php

declare(strict_types=1);

namespace Crm\SubscriptionsModule\Tests;

use Crm\ApplicationModule\Tests\DatabaseTestCase;
use Crm\SubscriptionsModule\Models\Builder\SubscriptionTypeBuilder;
use Crm\SubscriptionsModule\Repositories\SubscriptionTypeNamesRepository;
use Crm\SubscriptionsModule\Repositories\SubscriptionTypeTagsRepository;
use Crm\SubscriptionsModule\Repositories\SubscriptionTypesRepository;
use Crm\SubscriptionsModule\Repositories\SubscriptionsRepository;
use Crm\SubscriptionsModule\Scenarios\SubscriptionTypeHasTagCriteria;
use Crm\SubscriptionsModule\Seeders\SubscriptionExtensionMethodsSeeder;
use Crm\SubscriptionsModule\Seeders\SubscriptionLengthMethodSeeder;
use Crm\SubscriptionsModule\Seeders\SubscriptionTypeNamesSeeder;
use Crm\UsersModule\Models\Auth\UserManager;
use Crm\UsersModule\Repositories\UsersRepository;
use Exception;
use PHPUnit\Framework\Attributes\DataProvider;

class SubscriptionTypeHasTagCriteriaTest extends DatabaseTestCase
{
    public function requiredRepositories(): array
    {
        return [
            SubscriptionTypeNamesRepository::class,
            SubscriptionsRepository::class,
            SubscriptionTypesRepository::class,
            SubscriptionTypeTagsRepository::class,
            UsersRepository::class,
        ];
    }

    public function requiredSeeders(): array
    {
        return [
            SubscriptionExtensionMethodsSeeder::class,
            SubscriptionLengthMethodSeeder::class,
            SubscriptionTypeNamesSeeder::class,
        ];
    }

    public static function dataProviderForTestSubscriptionTypeHasTagCriteria(): array
    {
        return [
            'MatchingTag_ShouldReturnTrue' => [
                "hasTags" => ['senior', 'trial'],
                "testedTags" => [
                    ['senior', 'premium'],
                ],
                "expectedResult" => true,
            ],
            'DifferentTag_ShouldReturnFalse' => [
                "hasTags" => ['senior', 'trial'],
                "testedTags" => [
                    ['premium'],
                ],
                "expectedResult" => false,
            ],
            'EmptyTestedTags_ShouldThrowException' => [
                "hasTags" => ['senior', 'trial'],
                "testedTags" => [
                    [],
                ],
                "expectedResult" => null,
            ],
            'ChainedMatchingTags_ShouldReturnTrue' => [
                "hasTags" => ['senior', 'trial'],
                "testedTags" => [
                    ['senior', 'premium'],
                    ['trial'],
                ],
                "expectedResult" => true,
            ],
            'ChainedPartiallyDifferentTags_ShouldReturnFalse' => [
                "hasTags" => ['senior', 'trial'],
                "testedTags" => [
                    ['premium'],
                    ['trial'],
                ],
                "expectedResult" => false,
            ],
        ];
    }

    #[DataProvider('dataProviderForTestSubscriptionTypeHasTagCriteria')]
    public function testSubscriptionTypeHasTagCriteria(
        array $hasTags,
        array $testedTags,
        ?bool $expectedResult,
    ): void {
        [$subscriptionSelection, $subscriptionRow] = $this->prepareData($hasTags);

        /** @var SubscriptionTypeHasTagCriteria $criteria */
        $criteria = $this->inject(SubscriptionTypeHasTagCriteria::class);

        if ($expectedResult === null) {
            $this->expectException(Exception::class);
        }

        foreach ($testedTags as $tags) {
            $values = (object)['selection' => $tags];
            $criteria->addConditions(
                selection: $subscriptionSelection,
                paramValues: [SubscriptionTypeHasTagCriteria::KEY => $values],
                criterionItemRow: $subscriptionRow,
            );
        }

        if ($expectedResult === true) {
            $this->assertNotNull($subscriptionSelection->fetch());
        } elseif ($expectedResult === false) {
            $this->assertNull($subscriptionSelection->fetch());
        }
    }

    private function prepareData(array $hasTags): array
    {
        /** @var SubscriptionTypeBuilder $subscriptionTypeBuilder */
        $subscriptionTypeBuilder = $this->inject(SubscriptionTypeBuilder::class);

        $subscriptionTypeRow = $subscriptionTypeBuilder
            ->createNew()
            ->setNameAndUserLabel('Test')
            ->setActive(1)
            ->setPrice(1)
            ->setLength(31)
            ->save();

        /** @var SubscriptionTypeTagsRepository $subscriptionTypeTagsRepository */
        $subscriptionTypeTagsRepository = $this->getRepository(SubscriptionTypeTagsRepository::class);
        $subscriptionTypeTagsRepository->setTagsForSubscriptionType($subscriptionTypeRow, $hasTags);

        /** @var SubscriptionsRepository $subscriptionsRepository */
        $subscriptionsRepository = $this->getRepository(SubscriptionsRepository::class);

        /** @var UserManager $userManager */
        $userManager = $this->inject(UserManager::class);
        $userRow = $userManager->addNewUser('user@example.com');

        $subscriptionRow = $subscriptionsRepository->add(
            subscriptionType: $subscriptionTypeRow,
            isRecurrent: false,
            isPaid: false,
            user: $userRow,
            type: SubscriptionsRepository::TYPE_REGULAR,
        );

        $subscriptionSelection = $subscriptionsRepository->getTable()
            ->where('subscriptions.id', $subscriptionRow->id);

        return [$subscriptionSelection, $subscriptionRow];
    }
}
