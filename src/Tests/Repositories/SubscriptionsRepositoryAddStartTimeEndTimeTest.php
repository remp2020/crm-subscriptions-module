<?php
declare(strict_types=1);

namespace Crm\SubscriptionsModule\Tests\Repositories;

use Crm\ApplicationModule\Tests\DatabaseTestCase;
use Crm\SubscriptionsModule\Models\Builder\SubscriptionTypeBuilder;
use Crm\SubscriptionsModule\Models\Extension\ExtendSameContentAccess;
use Crm\SubscriptionsModule\Models\Length\FixDaysLengthMethod;
use Crm\SubscriptionsModule\Repositories\ContentAccessRepository;
use Crm\SubscriptionsModule\Repositories\SubscriptionExtensionMethodsRepository;
use Crm\SubscriptionsModule\Repositories\SubscriptionLengthMethodsRepository;
use Crm\SubscriptionsModule\Repositories\SubscriptionTypeContentAccessRepository;
use Crm\SubscriptionsModule\Repositories\SubscriptionTypeNamesRepository;
use Crm\SubscriptionsModule\Repositories\SubscriptionTypesRepository;
use Crm\SubscriptionsModule\Repositories\SubscriptionsRepository;
use Crm\SubscriptionsModule\Seeders\ContentAccessSeeder;
use Crm\SubscriptionsModule\Seeders\SubscriptionExtensionMethodsSeeder;
use Crm\SubscriptionsModule\Seeders\SubscriptionLengthMethodSeeder;
use Crm\SubscriptionsModule\Seeders\SubscriptionTypeNamesSeeder;
use Crm\UsersModule\Models\Auth\UserManager;
use Crm\UsersModule\Repositories\UsersRepository;
use Nette\Database\Table\ActiveRow;
use Nette\Utils\DateTime;
use PHPUnit\Framework\Attributes\DataProvider;

class SubscriptionsRepositoryAddStartTimeEndTimeTest extends DatabaseTestCase
{
    private const SUBSCRIPTION_LENGTH = 31;

    private ActiveRow $user;
    private ExtendSameContentAccess $extension;
    private SubscriptionsRepository $subscriptionsRepository;

    protected function requiredRepositories(): array
    {
        return [
            ContentAccessRepository::class,
            SubscriptionsRepository::class,
            SubscriptionTypeContentAccessRepository::class,
            SubscriptionTypesRepository::class,
            SubscriptionTypeNamesRepository::class,
            SubscriptionExtensionMethodsRepository::class,
            SubscriptionLengthMethodsRepository::class,
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

    protected function setUp(): void
    {
        parent::setUp();

        $this->subscriptionsRepository = $this->getRepository(SubscriptionsRepository::class);
        $this->extension = $this->inject(ExtendSameContentAccess::class); // will be needed to update setNow()
        /** @var UserManager $userManager */
        $userManager = $this->inject(UserManager::class);
        $this->user = $userManager->addNewUser('test@example.com');
    }

    protected function tearDown(): void
    {
        $this->subscriptionsRepository->setNow(null);
        $this->extension->setNow(null);

        parent::tearDown();
    }

    public static function dataProviderDates(): array
    {
        // setting now as different time than current NOW so we catch issue if some class works with different NOW than tests
        $now = (new DateTime())->modify('-3 days');
        $nowPlusMonth = $now->modifyClone('+' . self::SUBSCRIPTION_LENGTH . 'days');

        $nextWeek = $now->modifyClone('+7 day');
        $nextWeekPlusMonth = $nextWeek->modifyClone('+' . self::SUBSCRIPTION_LENGTH . 'days');

        $twoMonthsAgo = $now->modifyClone('-62 days');
        $twoMonthsAgoPlusMonth = $twoMonthsAgo->modifyClone('+' . self::SUBSCRIPTION_LENGTH . 'days');
        $twoMonthsAgoMinusMonth = $twoMonthsAgo->modifyClone('-' . self::SUBSCRIPTION_LENGTH . 'days');

        return [
            'SubscriptionType_NoFixedDates_-_Subscription_NoDates' => [
                'now' => $now,
                'subscriptionDates' => [
                    'start_time' => null,
                    'end_time' => null,
                ],
                'subscriptionTypeDates' => [
                    'fixed_start' => null,
                    'fixed_end' => null,
                ],
                'expectedDates' => [
                    'start_time' => $now,
                    'end_time' => $nowPlusMonth,
                ],
            ],

            // ****************************************************************
            // only subscription's start_time or end_time are set

            'SubscriptionType_NoFixedDates_-_Subscription_StartTimeSet' => [
                'now' => $now,
                'subscriptionDates' => [
                    'start_time' => $nextWeek,
                    'end_time' => null,
                ],
                'subscriptionTypeDates' => [
                    'fixed_start' => null,
                    'fixed_end' => null,
                ],
                'expectedDates' => [
                    'start_time' => $nextWeek,
                    'end_time' => $nextWeekPlusMonth,
                ],
            ],

            'SubscriptionType_NoFixedDates_-_Subscription_StartTimeSetInPast' => [
                'now' => $now,
                'subscriptionDates' => [
                    'start_time' => $twoMonthsAgo,
                    'end_time' => null,
                ],
                'subscriptionTypeDates' => [
                    'fixed_start' => null,
                    'fixed_end' => null,
                ],
                'expectedDates' => [
                    'start_time' => $twoMonthsAgo,
                    'end_time' => $twoMonthsAgoPlusMonth,
                ],
            ],

            'SubscriptionType_NoFixedDates_-_Subscription_EndTimeSet' => [
                'now' => $now,
                'subscriptionDates' => [
                    'start_time' => null,
                    'end_time' => $nextWeek,
                ],
                'subscriptionTypeDates' => [
                    'fixed_start' => null,
                    'fixed_end' => null,
                ],
                'expectedDates' => [
                    'start_time' => $now,
                    'end_time' => $nextWeek,
                ],
            ],

            'SubscriptionType_NoFixedDates_-_Subscription_EndTimeSetInPast' => [
                'now' => $now,
                'subscriptionDates' => [
                    'start_time' => null,
                    'end_time' => $twoMonthsAgo,
                ],
                'subscriptionTypeDates' => [
                    'fixed_start' => null,
                    'fixed_end' => null,
                ],
                'expectedDates' => [
                    'start_time' => $now,
                    // subscription.end_time is before start datetime
                    // end datetime is set to start datetime
                    'end_time' => $now,
                ],
            ],

            'SubscriptionType_NoFixedDates_-_Subscription_StartAndEndTimesSet' => [
                'now' => $now,
                'subscriptionDates' => [
                    'start_time' => $nextWeek,
                    'end_time' => $nowPlusMonth,
                ],
                'subscriptionTypeDates' => [
                    'fixed_start' => null,
                    'fixed_end' => null,
                ],
                'expectedDates' => [
                    'start_time' => $nextWeek,
                    'end_time' => $nowPlusMonth,
                ],
            ],

            'SubscriptionType_NoFixedDates_-_Subscription_StartAndEndTimesSetInPast' => [
                'now' => $now,
                'subscriptionDates' => [
                    'start_time' => $twoMonthsAgo,
                    'end_time' => $twoMonthsAgoPlusMonth,
                ],
                'subscriptionTypeDates' => [
                    'fixed_start' => null,
                    'fixed_end' => null,
                ],
                'expectedDates' => [
                    'start_time' => $twoMonthsAgo,
                    'end_time' => $twoMonthsAgoPlusMonth,
                ],
            ],

            // ****************************************************************
            // only subscription type's fixed dates are set

            'SubscriptionType_FixedStartSet_-_Subscription_NoDates' => [
                'now' => $now,
                'subscriptionDates' => [
                    'start_time' => null,
                    'end_time' => null,
                ],
                'subscriptionTypeDates' => [
                    'fixed_start' => $nextWeek,
                    'fixed_end' => null,
                ],
                'expectedDates' => [
                    'start_time' => $nextWeek,
                    'end_time' => $nextWeekPlusMonth,
                ],
            ],

            'SubscriptionType_FixedStartSetInPast_-_Subscription_NoDates' => [
                'now' => $now,
                'subscriptionDates' => [
                    'start_time' => null,
                    'end_time' => null,
                ],
                'subscriptionTypeDates' => [
                    'fixed_start' => $twoMonthsAgo,
                    'fixed_end' => null,
                ],
                'expectedDates' => [
                    // subscription_type.fixed_start is before current datetime
                    // start datetime is set to current datetime
                    'start_time' => $now,
                    // end datetime is calculated by extension method
                    'end_time' => $nowPlusMonth,
                ],
            ],

            'SubscriptionType_FixedEndSet_-_Subscription_NoDates' => [
                'now' => $now,
                'subscriptionDates' => [
                    'start_time' => null,
                    'end_time' => null,
                ],
                'subscriptionTypeDates' => [
                    'fixed_start' => null,
                    'fixed_end' => $nextWeekPlusMonth,
                ],
                'expectedDates' => [
                    'start_time' => $now,
                    'end_time' => $nextWeekPlusMonth,
                ],
            ],

            'SubscriptionType_FixedEndSetInPast_-_Subscription_NoDates' => [
                'now' => $now,
                'subscriptionDates' => [
                    'start_time' => null,
                    'end_time' => null,
                ],
                'subscriptionTypeDates' => [
                    'fixed_start' => null,
                    'fixed_end' => $twoMonthsAgo,
                ],
                'expectedDates' => [
                    'start_time' => $now,
                    // subscription_type.fixed_end is before start datetime
                    // end datetime is set to start datetime
                    'end_time' => $now,
                ],
            ],

            'SubscriptionType_BothFixedSet_-_Subscription_NoDates' => [
                'now' => $now,
                'subscriptionDates' => [
                    'start_time' => null,
                    'end_time' => null,
                ],
                'subscriptionTypeDates' => [
                    'fixed_start' => $nextWeek,
                    'fixed_end' => $nextWeekPlusMonth,
                ],
                'expectedDates' => [
                    'start_time' => $nextWeek,
                    'end_time' => $nextWeekPlusMonth,
                ],
            ],

            'SubscriptionType_BothFixedSetInPast_-_Subscription_NoDates' => [
                'now' => $now,
                'subscriptionDates' => [
                    'start_time' => null,
                    'end_time' => null,
                ],
                'subscriptionTypeDates' => [
                    'fixed_start' => $twoMonthsAgo,
                    'fixed_end' => $twoMonthsAgoPlusMonth,
                ],
                'expectedDates' => [
                    // subscription_type.fixed_start is before current datetime
                    // start datetime is set to current datetime
                    'start_time' => $now,
                    // subscription_type.fixed_end is before (re-evaluated) start datetime
                    // end datetime is set to start datetime
                    'end_time' => $now,
                ],
            ],

            // ****************************************************************
            // mix of subscription type fixed dates and subscription start / end dates

            'SubscriptionType_FixedStartInPast_-_Subscription_StartTime' => [
                'now' => $now,
                'subscriptionDates' => [
                    'start_time' => $nextWeek,
                    'end_time' => null,
                ],
                'subscriptionTypeDates' => [
                    'fixed_start' => $twoMonthsAgo,
                    'fixed_end' => null,
                ],
                'expectedDates' => [
                    // subscription.start_time is used
                    // subscription_type.fixed_start is ignored because it is in past
                    'start_time' => $nextWeek,
                    'end_time' => $nextWeekPlusMonth,
                ],
            ],

            'SubscriptionType_FixedStartInPast_-_Subscription_EndTime' => [
                'now' => $now,
                'subscriptionDates' => [
                    'start_time' => null,
                    'end_time' => $nextWeek,
                ],
                'subscriptionTypeDates' => [
                    'fixed_start' => $twoMonthsAgo,
                    'fixed_end' => null,
                ],
                'expectedDates' => [
                    // subscription_type.fixed_start is ignored because it is in past
                    // extension sets start datetime to current datetime
                    'start_time' => $now,
                    'end_time' => $nextWeek,
                ],
            ],

            'SubscriptionType_FixedEndInPast_-_Subscription_StartTime' => [
                'now' => $now,
                'subscriptionDates' => [
                    'start_time' => $nextWeek,
                    'end_time' => null,
                ],
                'subscriptionTypeDates' => [
                    'fixed_start' => null,
                    'fixed_end' => $twoMonthsAgo,
                ],
                'expectedDates' => [
                    'start_time' => $nextWeek,
                    // subscription_type.fixed_end is before start datetime
                    // end datetime is set to start datetime
                    'end_time' => $nextWeek,
                ],
            ],

            'SubscriptionType_FixedEndInPast_-_Subscription_EndTime' => [
                'now' => $now,
                'subscriptionDates' => [
                    'start_time' => null,
                    'end_time' => $nextWeek,
                ],
                'subscriptionTypeDates' => [
                    'fixed_start' => null,
                    'fixed_end' => $twoMonthsAgo,
                ],
                'expectedDates' => [
                    'start_time' => $now,
                    // subscription_type.fixed_end is ignored because subscription.end_time is "overriding parameter"
                    'end_time' => $nextWeek,
                ],
            ],

            'SubscriptionType_FixedStart_-_Subscription_StartTimeInPast' => [
                'now' => $now,
                'subscriptionDates' => [
                    'start_time' => $twoMonthsAgo,
                    'end_time' => null,
                ],
                'subscriptionTypeDates' => [
                    'fixed_start' => $nextWeek,
                    'fixed_end' => null,
                ],
                'expectedDates' => [
                    // subscription_type.fixed_start is ignored because subscription.start_time is "overriding parameter"
                    'start_time' => $twoMonthsAgo,
                    'end_time' => $twoMonthsAgoPlusMonth,
                ],
            ],

            'SubscriptionType_FixedStart_-_Subscription_EndTimeInPast' => [
                'now' => $now,
                'subscriptionDates' => [
                    'start_time' => null,
                    'end_time' => $twoMonthsAgo,
                ],
                'subscriptionTypeDates' => [
                    'fixed_start' => $nextWeek,
                    'fixed_end' => null,
                ],
                'expectedDates' => [
                    'start_time' => $nextWeek,
                    // subscription_type.fixed_end is before start datetime
                    // end datetime is set to start datetime
                    'end_time' => $nextWeek,
                ],
            ],

            'SubscriptionType_FixedEnd_-_Subscription_StartTimeInPast' => [
                'now' => $now,
                'subscriptionDates' => [
                    'start_time' => $twoMonthsAgo,
                    'end_time' => null,
                ],
                'subscriptionTypeDates' => [
                    'fixed_start' => null,
                    'fixed_end' => $nextWeek,
                ],
                'expectedDates' => [
                    'start_time' => $twoMonthsAgo,
                    // subscription_type.fixed_end overrides end datetime calculated by extension method
                    'end_time' => $nextWeek,
                ],
            ],

            'SubscriptionType_FixedEnd_-_Subscription_EndTimeInPast' => [
                'now' => $now,
                'subscriptionDates' => [
                    'start_time' => null,
                    'end_time' => $twoMonthsAgo,
                ],
                'subscriptionTypeDates' => [
                    'fixed_start' => null,
                    'fixed_end' => $nextWeek,
                ],
                'expectedDates' => [
                    'start_time' => $now,
                    // subscription_type.fixed_end is ignored because subscription.end_time is "overriding parameter"
                    // but subscription.end_time is before start datetime, end datetime is set to start datetime
                    'end_time' => $now,
                ],
            ],

            'SubscriptionType_BothFixedDatesSetInPast_-_Subscription_BothDatesSet' => [
                'now' => $now,
                'subscriptionDates' => [
                    'start_time' => $nextWeek,
                    'end_time' => $nextWeekPlusMonth,
                ],
                'subscriptionTypeDates' => [
                    'fixed_start' => $twoMonthsAgo,
                    'fixed_end' => $twoMonthsAgoPlusMonth,
                ],
                'expectedDates' => [
                    // subscription_type.fixed_start is ignored because it is in past
                    // extension sets start datetime to current datetime
                    'start_time' => $nextWeek,
                    // subscription_type.fixed_end is ignored because subscription.end_time is "overriding parameter"
                    'end_time' => $nextWeekPlusMonth,
                ],
            ],

            'SubscriptionType_BothFixedDatesSet_-_Subscription_BothDatesSetInPast' => [
                'now' => $now,
                'subscriptionDates' => [
                    'start_time' => $twoMonthsAgo,
                    'end_time' => $twoMonthsAgoPlusMonth,
                ],
                'subscriptionTypeDates' => [
                    'fixed_start' => $nextWeek,
                    'fixed_end' => $nextWeekPlusMonth,
                ],
                'expectedDates' => [
                    // subscription_type.fixed_start is ignored because subscription.start_time is "overriding parameter"
                    'start_time' => $twoMonthsAgo,
                    // subscription_type.fixed_end is ignored because subscription.end_time is "overriding parameter"
                    'end_time' => $twoMonthsAgoPlusMonth,
                ],
            ],

            'SubscriptionType_BothFixedDatesSet_-_Subscription_BothDatesSetInPast_EndTimeBeforeStartTime' => [
                'now' => $now,
                'subscriptionDates' => [
                    'start_time' => $twoMonthsAgo,
                    'end_time' => $twoMonthsAgoMinusMonth,
                ],
                'subscriptionTypeDates' => [
                    'fixed_start' => $nextWeek,
                    'fixed_end' => $nextWeekPlusMonth,
                ],
                'expectedDates' => [
                    // subscription_type.fixed_start is ignored because subscription.start_time is "overriding parameter"
                    'start_time' => $twoMonthsAgo,
                    // subscription_type.fixed_end is ignored because subscription.end_time is "overriding parameter"
                    // but subscription.end_time is before start datetime
                    // end datetime is set to start datetime
                    'end_time' => $twoMonthsAgo,
                ],
            ],
        ];
    }

    #[DataProvider('dataProviderDates')]
    public function testAdd(DateTime $now, array $subscriptionDates, array $subscriptionTypeDates, array $expectedDates)
    {
        // set same now for both subscription repository and extension
        $this->subscriptionsRepository->setNow($now);
        $this->extension->setNow($now);

        $subscriptionType = $this->getSubscriptionType(
            $subscriptionTypeDates['fixed_start'] ?? null,
            $subscriptionTypeDates['fixed_end'] ?? null,
        );

        $subscription = $this->subscriptionsRepository->add(
            $subscriptionType,
            false,
            true,
            $this->user,
            SubscriptionsRepository::TYPE_REGULAR,
            $subscriptionDates['start_time'] ?? null,
            $subscriptionDates['end_time'] ?? null,
        );

        $this->assertEquals(
            $expectedDates['start_time']?->format(DateTime::RFC3339),
            $subscription->start_time->format(DateTime::RFC3339),
            'Subscription has incorrect start time.',
        );
        $this->assertEquals(
            $expectedDates['end_time']?->format(DateTime::RFC3339),
            $subscription->end_time->format(DateTime::RFC3339),
            'Subscription has incorrect end time.',
        );
    }

    /** HELPER METHODS */

    private function getSubscriptionType(?DateTime $fixedStart, ?DateTime $fixedEnd)
    {
        /** @var SubscriptionTypeBuilder $subscriptionTypeBuilder */
        $subscriptionTypeBuilder = $this->inject(SubscriptionTypeBuilder::class);

        $subscriptionTypeRow = $subscriptionTypeBuilder
            ->createNew()
            ->setNameAndUserLabel(random_int(0, 9999))
            ->setContentAccessOption('web')
            ->setLengthMethod(FixDaysLengthMethod::METHOD_CODE)
            ->setExtensionMethod(ExtendSameContentAccess::METHOD_CODE)
            ->setFixedStart($fixedStart)
            ->setFixedEnd($fixedEnd)
            ->setActive(1)
            ->setPrice(1)
            ->setLength(self::SUBSCRIPTION_LENGTH)
            ->save();

        return $subscriptionTypeRow;
    }
}
