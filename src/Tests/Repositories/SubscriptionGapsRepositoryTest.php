<?php

declare(strict_types=1);

namespace Crm\SubscriptionsModule\Tests\Repositories;

use Crm\ApplicationModule\Tests\DatabaseTestCase;
use Crm\SubscriptionsModule\Repositories\ContentAccessRepository;
use Crm\SubscriptionsModule\Repositories\SubscriptionExtensionMethodsRepository;
use Crm\SubscriptionsModule\Repositories\SubscriptionGapsRepository;
use Crm\SubscriptionsModule\Repositories\SubscriptionLengthMethodsRepository;
use Crm\SubscriptionsModule\Repositories\SubscriptionTypeContentAccessRepository;
use Crm\SubscriptionsModule\Repositories\SubscriptionTypeNamesRepository;
use Crm\SubscriptionsModule\Repositories\SubscriptionTypesMetaRepository;
use Crm\SubscriptionsModule\Repositories\SubscriptionTypesRepository;
use Crm\SubscriptionsModule\Repositories\SubscriptionsRepository;
use Crm\SubscriptionsModule\Seeders\ContentAccessSeeder;
use Crm\SubscriptionsModule\Seeders\SubscriptionExtensionMethodsSeeder;
use Crm\SubscriptionsModule\Seeders\SubscriptionLengthMethodSeeder;
use Crm\SubscriptionsModule\Seeders\SubscriptionTypeNamesSeeder;
use Crm\SubscriptionsModule\Seeders\TestSeeder;
use Crm\UsersModule\Models\Auth\UserManager;
use Crm\UsersModule\Repositories\UsersRepository;
use Nette\Database\Table\ActiveRow;
use Nette\Utils\DateTime;

class SubscriptionGapsRepositoryTest extends DatabaseTestCase
{
    private SubscriptionGapsRepository $subscriptionGapsRepository;
    private SubscriptionsRepository $subscriptionsRepository;
    private SubscriptionTypesRepository $subscriptionTypesRepository;
    private ActiveRow $user;

    protected function requiredRepositories(): array
    {
        return [
            ContentAccessRepository::class,
            SubscriptionExtensionMethodsRepository::class,
            SubscriptionGapsRepository::class,
            SubscriptionLengthMethodsRepository::class,
            SubscriptionTypeContentAccessRepository::class,
            SubscriptionTypeNamesRepository::class,
            SubscriptionTypesMetaRepository::class,
            SubscriptionTypesRepository::class,
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
            TestSeeder::class,
        ];
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->subscriptionGapsRepository = $this->getRepository(SubscriptionGapsRepository::class);
        $this->subscriptionsRepository = $this->getRepository(SubscriptionsRepository::class);
        $this->subscriptionTypesRepository = $this->getRepository(SubscriptionTypesRepository::class);

        /** @var UserManager $userManager */
        $userManager = $this->inject(UserManager::class);
        $this->user = $userManager->addNewUser('test@example.com');
    }

    // recalculateForUsers — also covers mergeIntervals and findGaps indirectly. Returns users-with-gaps count.

    public function testRecalculateForUsersNoSubscriptionsReturnsZero(): void
    {
        $count = $this->subscriptionGapsRepository->recalculateForUsers([(int) $this->user->id]);
        $this->assertSame(0, $count);
        $this->assertNull($this->subscriptionGapsRepository->getForUser((int) $this->user->id));
    }

    public function testRecalculateForUsersSingleSubscriptionNoGaps(): void
    {
        $this->addSubscription('2020-01-01', '2020-02-01');

        $count = $this->subscriptionGapsRepository->recalculateForUsers([(int) $this->user->id]);
        $this->assertSame(0, $count);
    }

    public function testRecalculateForUsersTwoSubscriptionsGapEqualToMinGapDaysNoGap(): void
    {
        // gap = exactly 28 days; condition is strictly >, so not stored
        $this->addSubscription('2020-03-01', '2020-04-01');
        $this->addSubscription('2020-04-29', '2020-05-31');

        $count = $this->subscriptionGapsRepository->recalculateForUsers([(int) $this->user->id]);
        $this->assertSame(0, $count);
    }

    public function testRecalculateForUsersTwoSubscriptionsLongGapStoresOneGap(): void
    {
        // gap = 62 days (Jul 1 to Sep 1: July 31 + August 31); dates chosen to avoid DST transitions
        $this->addSubscription('2020-06-01', '2020-07-01');
        $this->addSubscription('2020-09-01', '2020-10-01');

        $count = $this->subscriptionGapsRepository->recalculateForUsers([(int) $this->user->id]);
        $this->assertSame(1, $count);

        $gap = $this->subscriptionGapsRepository->getForUser((int) $this->user->id);
        $this->assertNotNull($gap);
        $this->assertEquals(new DateTime('2020-07-01'), $gap->gap_start);
        $this->assertEquals(new DateTime('2020-09-01'), $gap->gap_end);
        $this->assertSame(62, $gap->gap_length_days);
    }

    public function testRecalculateForUsersOverlappingSubscriptionsNoFalseGap(): void
    {
        // Two concurrent subscription types that overlap — mergeIntervals should collapse them
        $this->addSubscription('2020-01-01', '2020-06-01');
        $this->addSubscription('2020-03-01', '2020-09-01');

        $count = $this->subscriptionGapsRepository->recalculateForUsers([(int) $this->user->id]);
        $this->assertSame(0, $count);
    }

    public function testRecalculateForUsersAdjacentSubscriptionsNoGap(): void
    {
        // end of first = start of second — no gap at all
        $this->addSubscription('2020-01-01', '2020-04-01');
        $this->addSubscription('2020-04-01', '2020-07-01');

        $count = $this->subscriptionGapsRepository->recalculateForUsers([(int) $this->user->id]);
        $this->assertSame(0, $count);
    }

    public function testRecalculateForUsersThreeSubscriptionsTwoLongGapsStoresBothGaps(): void
    {
        $this->addSubscription('2020-01-01', '2020-02-01');
        $this->addSubscription('2020-04-01', '2020-05-01'); // gap: Feb 1 – Apr 1 = 60 days
        $this->addSubscription('2020-07-01', '2020-08-01'); // gap: May 1 – Jul 1 = 61 days

        $count = $this->subscriptionGapsRepository->recalculateForUsers([(int) $this->user->id]);
        $this->assertSame(1, $count); // one user with gaps
        $this->assertSame(2, $this->countGapsForUser()); // two gap rows
    }

    public function testRecalculateForUsersReplacesExistingGaps(): void
    {
        $this->addSubscription('2020-01-01', '2020-02-01');
        $this->addSubscription('2020-04-01', '2020-05-01');

        $this->subscriptionGapsRepository->recalculateForUsers([(int) $this->user->id]);
        $this->assertSame(1, $this->countGapsForUser());

        // bridging subscription fills the gap
        $this->addSubscription('2020-02-01', '2020-04-02');
        $count = $this->subscriptionGapsRepository->recalculateForUsers([(int) $this->user->id]);

        $this->assertSame(0, $count);
        $this->assertSame(0, $this->countGapsForUser());
    }

    public function testRecalculateForUsersSkipDeleteKeepsExistingGaps(): void
    {
        // skipDelete=true used by command in full-rebuild mode (after deleteAll). Verifies no per-user delete happens.
        $this->subscriptionGapsRepository->add(
            (int) $this->user->id,
            new DateTime('2019-01-01'),
            new DateTime('2019-06-01'),
            151,
        );
        $this->addSubscription('2020-01-01', '2020-02-01'); // no gaps for this user

        $count = $this->subscriptionGapsRepository->recalculateForUsers([(int) $this->user->id], skipDelete: true);
        $this->assertSame(0, $count);
        $this->assertSame(1, $this->countGapsForUser()); // pre-existing gap untouched
    }

    public function testRecalculateForUsersMultipleUsersInOneCall(): void
    {
        /** @var UserManager $userManager */
        $userManager = $this->inject(UserManager::class);
        $otherUser = $userManager->addNewUser('other@example.com');

        // user A: one long gap
        $this->addSubscription('2020-01-01', '2020-02-01');
        $this->addSubscription('2020-05-01', '2020-06-01');

        // user B: no gaps
        $subscriptionType = $this->subscriptionTypesRepository->findByCode(TestSeeder::SUBSCRIPTION_TYPE_WEB_YEAR);
        $this->subscriptionsRepository->add(
            $subscriptionType,
            false,
            true,
            $otherUser,
            SubscriptionsRepository::TYPE_REGULAR,
            new DateTime('2020-01-01'),
            new DateTime('2020-02-01'),
        );

        $count = $this->subscriptionGapsRepository->recalculateForUsers([
            (int) $this->user->id,
            (int) $otherUser->id,
        ]);
        $this->assertSame(1, $count);
        $this->assertSame(1, $this->countGapsForUser());
        $this->assertNull($this->subscriptionGapsRepository->getForUser((int) $otherUser->id));
    }

    // hasReactivatingGap

    public function testHasReactivatingGapNoGapsReturnsFalse(): void
    {
        $result = $this->subscriptionGapsRepository->hasReactivatingGap(
            userId: (int) $this->user->id,
            subscriptionStart: new DateTime('2020-04-01'),
            minGapDays: 28,
            maxGapDays: 90,
        );
        $this->assertFalse($result);
    }

    public function testHasReactivatingGapGapWithinWindowAndLongEnoughReturnsTrue(): void
    {
        // gap_end = 2020-04-01 = subscriptionStart; window = 90 days back = 2020-01-01; gap_length = 76 > 28
        $this->subscriptionGapsRepository->add(
            (int) $this->user->id,
            new DateTime('2020-01-15'),
            new DateTime('2020-04-01'),
            76,
        );

        $result = $this->subscriptionGapsRepository->hasReactivatingGap(
            userId: (int) $this->user->id,
            subscriptionStart: new DateTime('2020-04-01'),
            minGapDays: 28,
            maxGapDays: 90,
        );
        $this->assertTrue($result);
    }

    public function testHasReactivatingGapGapTooShortReturnsFalse(): void
    {
        // gap_length = 28 = minGapDays; condition is strictly >, so false
        $this->subscriptionGapsRepository->add(
            (int) $this->user->id,
            new DateTime('2020-03-04'),
            new DateTime('2020-04-01'),
            28,
        );

        $result = $this->subscriptionGapsRepository->hasReactivatingGap(
            userId: (int) $this->user->id,
            subscriptionStart: new DateTime('2020-04-01'),
            minGapDays: 28,
            maxGapDays: 90,
        );
        $this->assertFalse($result);
    }

    public function testHasReactivatingGapGapEndBeforeWindowReturnsFalse(): void
    {
        // windowStart = 2020-04-01 - 90 days = 2020-01-01; gap_end = 2019-12-31 < windowStart
        $this->subscriptionGapsRepository->add(
            (int) $this->user->id,
            new DateTime('2019-10-01'),
            new DateTime('2019-12-31'),
            91,
        );

        $result = $this->subscriptionGapsRepository->hasReactivatingGap(
            userId: (int) $this->user->id,
            subscriptionStart: new DateTime('2020-04-01'),
            minGapDays: 28,
            maxGapDays: 90,
        );
        $this->assertFalse($result);
    }

    public function testHasReactivatingGapGapEndAfterSubscriptionStartReturnsFalse(): void
    {
        // gap_end > subscriptionStart — gap not yet closed when subscription started
        $this->subscriptionGapsRepository->add(
            (int) $this->user->id,
            new DateTime('2020-03-01'),
            new DateTime('2020-05-01'),
            61,
        );

        $result = $this->subscriptionGapsRepository->hasReactivatingGap(
            userId: (int) $this->user->id,
            subscriptionStart: new DateTime('2020-04-01'),
            minGapDays: 28,
            maxGapDays: 90,
        );
        $this->assertFalse($result);
    }

    // helpers

    private function addSubscription(string $start, string $end): ActiveRow
    {
        $subscriptionType = $this->subscriptionTypesRepository->findByCode(TestSeeder::SUBSCRIPTION_TYPE_WEB_YEAR);
        return $this->subscriptionsRepository->add(
            $subscriptionType,
            false,
            true,
            $this->user,
            SubscriptionsRepository::TYPE_REGULAR,
            new DateTime($start),
            new DateTime($end),
        );
    }

    private function countGapsForUser(): int
    {
        return (int) $this->subscriptionGapsRepository->getTable()
            ->where('user_id', $this->user->id)
            ->count('*');
    }
}
