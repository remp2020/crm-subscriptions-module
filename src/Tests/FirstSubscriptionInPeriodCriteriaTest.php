<?php

namespace Crm\SubscriptionsModule\Tests;

use Crm\ApplicationModule\Tests\DatabaseTestCase;
use Crm\SubscriptionsModule\Models\Builder\SubscriptionTypeBuilder;
use Crm\SubscriptionsModule\Repositories\ContentAccessRepository;
use Crm\SubscriptionsModule\Repositories\SubscriptionTypeContentAccessRepository;
use Crm\SubscriptionsModule\Repositories\SubscriptionTypeItemsRepository;
use Crm\SubscriptionsModule\Repositories\SubscriptionTypesRepository;
use Crm\SubscriptionsModule\Repositories\SubscriptionsRepository;
use Crm\SubscriptionsModule\Scenarios\FirstSubscriptionInPeriodCriteria;
use Crm\SubscriptionsModule\Seeders\ContentAccessSeeder;
use Crm\SubscriptionsModule\Seeders\SubscriptionExtensionMethodsSeeder;
use Crm\SubscriptionsModule\Seeders\SubscriptionLengthMethodSeeder;
use Crm\SubscriptionsModule\Seeders\SubscriptionTypeNamesSeeder;
use Crm\UsersModule\Models\Auth\UserManager;
use Crm\UsersModule\Repositories\UsersRepository;
use Nette\Database\Table\Selection;
use Nette\Utils\DateTime;
use PHPUnit\Framework\Attributes\DataProvider;

class FirstSubscriptionInPeriodCriteriaTest extends DatabaseTestCase
{
    protected function requiredRepositories(): array
    {
        return [
            ContentAccessRepository::class,
            SubscriptionTypeContentAccessRepository::class,
            SubscriptionTypeItemsRepository::class,
            SubscriptionTypesRepository::class,
            SubscriptionsRepository::class,
            UsersRepository::class,
        ];
    }

    protected function requiredSeeders(): array
    {
        return [
            SubscriptionExtensionMethodsSeeder::class,
            SubscriptionLengthMethodSeeder::class,
            SubscriptionTypeNamesSeeder::class,
            ContentAccessSeeder::class,
        ];
    }

    protected function setUp(): void
    {
        parent::setUp();

        // we need 4th content access for proper test (rest is seeded by ContentAccessSeeder)
        $contentAccessRepository = $this->getRepository(ContentAccessRepository::class);
        $name = 'club';
        if (!$contentAccessRepository->exists($name)) {
            $contentAccessRepository->add($name, 'Club access', 'label label-danger', 400);
        }
    }

    #[DataProvider('dataProviderForFirstSubscriptionInPeriodCriteria')]
    public function testFirstSubscriptionInPeriodCriteria(
        string $currentStartTime,
        array $currentContentAccesses,
        ?string $previousStartTime,
        array $previousContentAccesses,
        mixed $intervalDays,
        array $contentAccesses,
        bool $expectedValue,
        ?\Exception $expectedException = null
    ) {
        /** @var Selection $subscriptionSelection */
        [$subscriptionSelection, $subscriptionRow] = $this->prepareData(
            $currentStartTime,
            $currentContentAccesses,
            $previousStartTime,
            $previousContentAccesses,
        );

        /** @var FirstSubscriptionInPeriodCriteria $criteria */
        $criteria = $this->inject(FirstSubscriptionInPeriodCriteria::class);
        $values = (object)['selection' => $intervalDays];

        if ($expectedException !== null) {
            $this->expectExceptionObject($expectedException);
        }

        $conditions = [FirstSubscriptionInPeriodCriteria::PERIOD_KEY => $values];

        if (!empty($contentAccesses)) {
            $conditions[FirstSubscriptionInPeriodCriteria::CONTENT_ACCESS_KEY] = (object)['selection' => $contentAccesses];
        }

        $criteria->addConditions($subscriptionSelection, $conditions, $subscriptionRow);

        if ($expectedValue) {
            $this->assertNotNull($subscriptionSelection->fetch());
        } else {
            $this->assertNull($subscriptionSelection->fetch());
        }
    }


    public static function dataProviderForFirstSubscriptionInPeriodCriteria(): array
    {
        return [
            'SingleSubscription_ShouldBeTrue' => [
                'currentStartTime' => 'now',
                'currentContentAccesses' => ['web'],
                'previousStartTime' => null,
                'previousContentAccesses' => [],
                'intervalDays' => 50,
                'contentAccesses' => [],
                'expectedValue' => true,
            ],
            'PreviousSubscriptionWithinPeriod_ShouldBeFalse' => [
                'currentStartTime' => 'now',
                'currentContentAccesses' => ['web'],
                'previousStartTime' => '-20 days',
                'previousContentAccesses' => ['web'],
                'intervalDays' => 50,
                'contentAccesses' => [],
                'expectedValue' => false,
            ],
            'PreviousSubscriptionOutsidePeriod_ShouldBeTrue' => [
                'currentStartTime' => 'now',
                'currentContentAccesses' => ['web'],
                'previousStartTime' => '-100 days',
                'previousContentAccesses' => ['web'],
                'intervalDays' => 50,
                'contentAccesses' => [],
                'expectedValue' => true,
            ],
            'Fail_ValueIsNegative' => [
                'currentStartTime' => 'now',
                'currentContentAccesses' => ['web'],
                'previousStartTime' => null,
                'previousContentAccesses' => [],
                'intervalDays' => -10,
                'contentAccesses' => [],
                'expectedValue' => false,
                'expectedException' => new \Exception("Provided value [-10] for number of days is not valid positive integer."),
            ],
            'Fail_ValueIsString' => [
                'currentStartTime' => 'now',
                'currentContentAccesses' => ['web'],
                'previousStartTime' => null,
                'previousContentAccesses' => [],
                'intervalDays' => 'random_string',
                'contentAccesses' => [],
                'expectedValue' => false,
                'expectedException' => new \Exception("Provided value [random_string] for number of days is not valid positive integer."),
            ],

            // with content access filter
            'ContentAccessFilter_SingleSubscription_ShouldBeTrue' => [
                'currentStartTime' => 'now',
                'currentContentAccesses' => ['web'],
                'previousStartTime' => null,
                'previousContentAccesses' => [],
                'intervalDays' => 50,
                'contentAccesses' => ['web'],
                'expectedValue' => true,
            ],
            'ContentAccessFilter_PreviousSubscriptionWithinPeriod_SameContentAccess_ShouldBeFalse' => [
                'currentStartTime' => 'now',
                'currentContentAccesses' => ['web'],
                'previousStartTime' => '-20 days',
                'previousContentAccesses' => ['web'],
                'intervalDays' => 50,
                'contentAccesses' => ['web'],
                'expectedValue' => false,
            ],
            'ContentAccessFilter_PreviousSubscriptionWithinPeriod_DifferentContentAccess_ShouldBeTrue' => [
                'currentStartTime' => 'now',
                'currentContentAccesses' => ['web', 'print'],
                'previousStartTime' => '-20 days',
                'previousContentAccesses' => ['web'],
                'intervalDays' => 50,
                'contentAccesses' => ['print'],
                'expectedValue' => true,
            ],
            'ContentAccessFilter_CurrentNotMatchingContentAccessSelection_ShouldBeFalse' => [
                'currentStartTime' => 'now',
                'currentContentAccesses' => ['web', 'print'],
                'previousStartTime' => '-20 days',
                'previousContentAccesses' => ['web'],
                'intervalDays' => 50,
                'contentAccesses' => ['mobile'],
                'expectedValue' => false,
            ],
            'ContentAccessFilter_CurrentNotMatchingContentAccessSelection_PreviousIsMatching_ShouldBeFalse' => [
                'currentStartTime' => 'now',
                'currentContentAccesses' => ['web', 'print'],
                'previousStartTime' => '-20 days',
                'previousContentAccesses' => ['web', 'mobile'],
                'intervalDays' => 50,
                'contentAccesses' => ['mobile'],
                'expectedValue' => false,
            ],

            // multiple content accesses
            'ContentAccessFilter_TwoContentAccesses_SingleSubscription_ShouldBeTrue' => [
                'currentStartTime' => 'now',
                'currentContentAccesses' => ['web', 'print'],
                'previousStartTime' => null,
                'previousContentAccesses' => [],
                'intervalDays' => 50,
                'contentAccesses' => ['web', 'print'],
                'expectedValue' => true,
            ],
            'ContentAccessFilter_TwoContentAccesses_PreviousSubscriptionWithinPeriod_SameContentAccess_ShouldBeFalse' => [
                'currentStartTime' => 'now',
                'currentContentAccesses' => ['web', 'print'],
                'previousStartTime' => '-20 days',
                'previousContentAccesses' => ['web', 'print'],
                'intervalDays' => 50,
                'contentAccesses' => ['web', 'print'], // matched both web and print on both subscriptions; will be false
                'expectedValue' => false,
            ],
            'ContentAccessFilter_TwoContentAccesses_PreviousSubscriptionWithinPeriod_DifferentContentAccess_ShouldBeTrue' => [
                'currentStartTime' => 'now',
                'currentContentAccesses' => ['web', 'print'],
                'previousStartTime' => '-20 days',
                'previousContentAccesses' => ['web', 'mobile'],
                'intervalDays' => 50,
                'contentAccesses' => ['print'], // matched in current but no previous; will be true
                'expectedValue' => true,
            ],
            'ContentAccessFilter_TwoContentAccesses_PreviousSubscriptionWithinPeriod_DifferentContentAccess_2ContentAccesses_ShouldBeFalse' => [
                'currentStartTime' => 'now',
                'currentContentAccesses' => ['web', 'print'],
                'previousStartTime' => '-20 days',
                'previousContentAccesses' => ['web', 'mobile'],
                'intervalDays' => 50,
                'contentAccesses' => ['web', 'print'], // matched web on previous; will be false
                'expectedValue' => false,
            ],
            'ContentAccessFilter_TwoContentAccesses_CurrentNotMatchingContentAccessSelection_ShouldBeFalse' => [
                'currentStartTime' => 'now',
                'currentContentAccesses' => ['web', 'print'],
                'previousStartTime' => '-20 days',
                'previousContentAccesses' => ['web', 'mobile'],
                'intervalDays' => 50,
                'contentAccesses' => ['club'], // not matched in current
                'expectedValue' => false,
            ],
            'ContentAccessFilter_TwoContentAccesses_CurrentNotMatchingContentAccessSelection_ButPreviousIsMatching_ShouldBeFalse' => [
                'currentStartTime' => 'now',
                'currentContentAccesses' => ['web', 'print'],
                'previousStartTime' => '-20 days',
                'previousContentAccesses' => ['web', 'club'],
                'intervalDays' => 50,
                'contentAccesses' => ['club'], // not matched in current
                'expectedValue' => false,
            ],
        ];
    }

    private function prepareData(
        string $currentStartTime,
        array $currentContentAccess,
        ?string $previousStartTime,
        array $previousContentAccess,
    ) {
        /** @var UserManager $userManager */
        $userManager = $this->inject(UserManager::class);
        $userRow = $userManager->addNewUser('test@test.sk');

        /** @var SubscriptionTypeBuilder $subscriptionTypeBuilder */
        $subscriptionTypeBuilder = $this->inject(SubscriptionTypeBuilder::class);
        $subscriptionTypeRow = $subscriptionTypeBuilder->createNew()
            ->setLength(10)
            ->setPrice(1)
            ->setActive(1)
        ;

        $previousSubscriptionTypeRow = clone($subscriptionTypeRow)
            ->setContentAccessOption(...$previousContentAccess)
            ->setNameAndUserLabel('test previous')
            ->save();

        $currentSubscriptionTypeRow = clone($subscriptionTypeRow)
            ->setNameAndUserLabel('test current')
            ->setContentAccessOption(...$currentContentAccess)
            ->save();

        /** @var SubscriptionsRepository $subscriptionsRepository */
        $subscriptionsRepository = $this->getRepository(SubscriptionsRepository::class);

        if ($previousStartTime) {
            $subscriptionsRepository->add(
                subscriptionType: $previousSubscriptionTypeRow,
                isRecurrent: false,
                isPaid: false,
                user: $userRow,
                startTime: DateTime::from($previousStartTime),
            );
        }
        $subscriptionRow = $subscriptionsRepository->add(
            subscriptionType: $currentSubscriptionTypeRow,
            isRecurrent: false,
            isPaid: false,
            user: $userRow,
            startTime: DateTime::from($currentStartTime),
        );

        $subscriptionSelection = $subscriptionsRepository->getTable()
            ->where(['subscriptions.id' => $subscriptionRow->id]);

        return [$subscriptionSelection, $subscriptionRow];
    }
}
