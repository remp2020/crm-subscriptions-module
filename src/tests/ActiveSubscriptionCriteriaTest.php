<?php

namespace Crm\SubscriptionsModule\Tests;

use Crm\ApplicationModule\Criteria\CriteriaStorage;
use Crm\ApplicationModule\Tests\DatabaseTestCase;
use Crm\SegmentModule\Criteria\Generator;
use Crm\SegmentModule\Segment;
use Crm\SegmentModule\SegmentQuery;
use Crm\SubscriptionsModule\Builder\SubscriptionTypeBuilder;
use Crm\SubscriptionsModule\Repository\SubscriptionsRepository;
use Crm\SubscriptionsModule\Seeders\ContentAccessSeeder;
use Crm\SubscriptionsModule\Seeders\SubscriptionExtensionMethodsSeeder;
use Crm\SubscriptionsModule\Seeders\SubscriptionLengthMethodSeeder;
use Crm\SubscriptionsModule\Seeders\SubscriptionTypeNamesSeeder;
use Crm\UsersModule\Auth\UserManager;
use Crm\UsersModule\Repository\UsersRepository;
use Nette\Database\Table\ActiveRow;
use Nette\Utils\DateTime;

class ActiveSubscriptionCriteriaTest extends DatabaseTestCase
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
            $this->inject(\Crm\SubscriptionsModule\Segment\UserActiveSubscriptionCriteria::class)
        );
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

    /**
     * @dataProvider dataProviderForTestCriteria
     */
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
                                ]
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

    public function dataProviderForTestCriteria(): array
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
            DateTime::from($endTime)
        );

        return $subscriptionRow;
    }
}
