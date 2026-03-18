<?php

namespace Crm\SubscriptionsModule\Tests;

use Crm\ApplicationModule\Models\Criteria\CriteriaStorage;
use Crm\ApplicationModule\Tests\DatabaseTestCase;
use Crm\SegmentModule\Models\Criteria\Generator;
use Crm\SegmentModule\Models\Segment;
use Crm\SegmentModule\Models\SegmentQuery;
use Crm\SubscriptionsModule\Models\Builder\SubscriptionTypeBuilder;
use Crm\SubscriptionsModule\Repositories\SubscriptionTypeTagsRepository;
use Crm\SubscriptionsModule\Repositories\SubscriptionsRepository;
use Crm\SubscriptionsModule\Seeders\ContentAccessSeeder;
use Crm\SubscriptionsModule\Seeders\SubscriptionExtensionMethodsSeeder;
use Crm\SubscriptionsModule\Seeders\SubscriptionLengthMethodSeeder;
use Crm\SubscriptionsModule\Seeders\SubscriptionTypeNamesSeeder;
use Crm\SubscriptionsModule\Segment\UserWithSubscriptionCriteria;
use Crm\UsersModule\Models\Auth\UserManager;
use Crm\UsersModule\Repositories\UsersRepository;
use Nette\Database\Table\ActiveRow;
use Nette\Utils\DateTime;
use PHPUnit\Framework\Attributes\DataProvider;

class WithSubscriptionCriteriaTest extends DatabaseTestCase
{
    private const CRITERIA_KEY = 'tested_criteria';

    /** @var SubscriptionsRepository */
    private $subscriptionsRepository;

    /** @var Generator */
    private $generator;

    public function setUp(): void
    {
        parent::setUp();

        $this->subscriptionsRepository = $this->getRepository(SubscriptionsRepository::class);
        $this->generator = $this->inject(Generator::class);

        $criteriaStorage = $this->inject(CriteriaStorage::class);
        $criteriaStorage->register(
            'users',
            self::CRITERIA_KEY,
            $this->inject(UserWithSubscriptionCriteria::class),
        );
    }

    protected function requiredRepositories(): array
    {
        return [
            SubscriptionsRepository::class,
            UsersRepository::class,
            SubscriptionTypeTagsRepository::class,
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
    public function testCriteria($startTime, $endTime, $interval, $negation, $result)
    {
        $this->prepareData('user@example.com', $startTime, $endTime);

        $queryString = $this->generator->process('users', [
            'version' => 1,
            'nodes' => [
                [
                    'type' => 'operator',
                    'operator' => 'AND',
                    'nodes' => [
                        [
                            'type' => 'criteria',
                            'key' => self::CRITERIA_KEY,
                            'negation' => $negation,
                            'values' => [
                                'active_at' => [
                                    'type' => 'interval',
                                    'interval' => $interval,
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        $query = new SegmentQuery($queryString, 'users', 'users.id');
        $segment = new Segment($this->subscriptionsRepository->getDatabase(), $query);

        $this->assertEquals((int) $result, $segment->totalCount());
    }

    public static function dataProviderForTestCriteria(): array
    {
        return [
            '--====--NOW++++++++' => [
                'startTime' => '-45 days',
                'endTime' => '-15 days',
                'interval' => [
                    'gte' => ['unit' => 'now'],
                ],
                'negation' => false,
                'result' => false,
            ],
            '------==NOW==++++++' => [
                'startTime' => '-15 days',
                'endTime' => '+15 days',
                'interval' => [
                    'gte' => ['unit' => 'now'],
                ],
                'negation' => false,
                'result' => true,
            ],
            '--------NOW++====++' => [
                'startTime' => '+15 days',
                'endTime' => '+45 days',
                'interval' => [
                    'gte' => ['unit' => 'now'],
                ],
                'negation' => false,
                'result' => true,
            ],

            // negations

            'NEGATION: --====--NOW++++++++' => [
                'startTime' => '-45 days',
                'endTime' => '-15 days',
                'interval' => [
                    'gte' => ['unit' => 'now'],
                ],
                'negation' => true,
                'result' => true,
            ],
            'NEGATION: ------==NOW==++++++' => [
                'startTime' => '-15 days',
                'endTime' => '+15 days',
                'interval' => [
                    'gte' => ['unit' => 'now'],
                ],
                'negation' => true,
                'result' => false,
            ],
            'NEGATION: --------NOW++====++' => [
                'startTime' => '+15 days',
                'endTime' => '+45 days',
                'interval' => [
                    'gte' => ['unit' => 'now'],
                ],
                'negation' => true,
                'result' => false,
            ],
        ];
    }

    #[DataProvider('dataProviderForTestSubscriptionTypeTags')]
    public function testSubscriptionTypeTags(
        array $subscriptionTypesWithTags,
        ?array $filterTags,
        int $expectedCount,
    ) {
        $subscriptionTypeBuilder = $this->inject(SubscriptionTypeBuilder::class);
        $userManager = $this->inject(UserManager::class);

        $userIndex = 0;
        foreach ($subscriptionTypesWithTags as $name => $tags) {
            $builder = $subscriptionTypeBuilder
                ->createNew()
                ->setNameAndUserLabel($name)
                ->setActive(1)
                ->setPrice(1)
                ->setLength(31)
                ->setContentAccessOption('web');

            if ($tags !== null) {
                $builder->setTags(...$tags);
            }

            $subscriptionType = $builder->save();

            $user = $userManager->addNewUser("user{$userIndex}@example.com");
            $this->subscriptionsRepository->add(
                $subscriptionType,
                false,
                false,
                $user,
                SubscriptionsRepository::TYPE_REGULAR,
                DateTime::from('-5 days'),
                DateTime::from('+25 days'),
            );
            $userIndex++;
        }

        // Build query
        $nodes = [
            'type' => 'criteria',
            'key' => self::CRITERIA_KEY,
            'negation' => false,
            'values' => [
                'active_at' => [
                    'type' => 'interval',
                    'interval' => [
                        'gte' => ['unit' => 'now'],
                    ],
                ],
            ],
        ];

        if ($filterTags !== null) {
            $nodes['values']['subscription_type_tags'] = $filterTags;
        }

        $queryString = $this->generator->process('users', [
            'version' => 1,
            'nodes' => [
                [
                    'type' => 'operator',
                    'operator' => 'AND',
                    'nodes' => [$nodes],
                ],
            ],
        ]);

        $query = new SegmentQuery($queryString, 'users', 'users.id');
        $segment = new Segment($this->subscriptionsRepository->getDatabase(), $query);

        $this->assertEquals($expectedCount, $segment->totalCount());
    }

    public static function dataProviderForTestSubscriptionTypeTags(): array
    {
        return [
            'FilterByTrialTag_ShouldReturnOneUser' => [
                'subscriptionTypesWithTags' => [
                    'trial' => ['trial', 'test'],
                    'test' => ['test'],
                    'noTags' => null,
                ],
                'filterTags' => ['trial'],
                'expectedCount' => 1,
            ],
            'FilterByTestTag_ShouldReturnTwoUsers' => [
                'subscriptionTypesWithTags' => [
                    'trial' => ['trial', 'test'],
                    'test' => ['test'],
                    'noTags' => null,
                ],
                'filterTags' => ['test'],
                'expectedCount' => 2,
            ],
            'FilterByMultipleTags_ShouldReturnTwoUsers' => [
                'subscriptionTypesWithTags' => [
                    'trial' => ['trial'],
                    'test' => ['test'],
                    'noTags' => null,
                ],
                'filterTags' => ['trial', 'test'],
                'expectedCount' => 2,
            ],
            'FilterByMultipleTags_ShouldReturnThreeUsers' => [
                'subscriptionTypesWithTags' => [
                    'trial' => ['trial'],
                    'test' => ['test'],
                    'extra' => ['extra', 'test'],
                ],
                'filterTags' => ['trial', 'test'],
                'expectedCount' => 3,
            ],
            'NoTagFilter_ShouldReturnAllUsers' => [
                'subscriptionTypesWithTags' => [
                    'trial' => ['trial'],
                    'test' => ['test'],
                    'noTags' => null,
                ],
                'filterTags' => null,
                'expectedCount' => 3,
            ],
        ];
    }

    private function prepareData($userEmail, string $startTime, string $endTime): ActiveRow
    {
        /** @var SubscriptionTypeBuilder $subscriptionTypeBuilder */
        $subscriptionTypeBuilder = $this->inject(SubscriptionTypeBuilder::class);

        $subscriptionTypeRow = $subscriptionTypeBuilder
            ->createNew()
            ->setNameAndUserLabel('Test')
            ->setActive(1)
            ->setPrice(1)
            ->setLength(31)
            ->setContentAccessOption('web')
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
            $userRow,
            SubscriptionsRepository::TYPE_REGULAR,
            DateTime::from($startTime),
            DateTime::from($endTime),
        );

        return $subscriptionRow;
    }
}
