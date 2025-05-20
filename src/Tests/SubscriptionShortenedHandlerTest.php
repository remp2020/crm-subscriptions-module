<?php

namespace Crm\SubscriptionsModule\Tests;

use Crm\ApplicationModule\Tests\DatabaseTestCase;
use Crm\SubscriptionsModule\Events\SubscriptionShortenedEvent;
use Crm\SubscriptionsModule\Events\SubscriptionShortenedHandler;
use Crm\SubscriptionsModule\Repositories\SubscriptionExtensionMethodsRepository;
use Crm\SubscriptionsModule\Repositories\SubscriptionLengthMethodsRepository;
use Crm\SubscriptionsModule\Repositories\SubscriptionTypesMetaRepository;
use Crm\SubscriptionsModule\Repositories\SubscriptionTypesRepository;
use Crm\SubscriptionsModule\Repositories\SubscriptionsRepository;
use Crm\SubscriptionsModule\Seeders\SubscriptionExtensionMethodsSeeder;
use Crm\SubscriptionsModule\Seeders\SubscriptionLengthMethodSeeder;
use Crm\SubscriptionsModule\Seeders\SubscriptionTypeNamesSeeder;
use Crm\SubscriptionsModule\Seeders\TestSeeder;
use Crm\UsersModule\Models\Auth\UserManager;
use Crm\UsersModule\Repositories\UsersRepository;
use Nette\Database\Table\ActiveRow;

class SubscriptionShortenedHandlerTest extends DatabaseTestCase
{
    private SubscriptionsRepository $subscriptionsRepository;
    private SubscriptionTypesRepository $subscriptionTypesRepository;
    private UserManager $userManager;
    private SubscriptionShortenedHandler $subscriptionShortenedHandler;

    protected function requiredRepositories(): array
    {
        return [
            SubscriptionsRepository::class,
            SubscriptionTypesRepository::class,
            SubscriptionExtensionMethodsRepository::class,
            SubscriptionLengthMethodsRepository::class,
            SubscriptionTypesMetaRepository::class,
            UsersRepository::class,
        ];
    }

    protected function requiredSeeders(): array
    {
        return [
            SubscriptionExtensionMethodsSeeder::class,
            SubscriptionLengthMethodSeeder::class,
            SubscriptionTypeNamesSeeder::class,
            TestSeeder::class,
        ];
    }

    public function setUp(): void
    {
        parent::setUp();
        $this->subscriptionsRepository = $this->getRepository(SubscriptionsRepository::class);
        $this->subscriptionTypesRepository = $this->getRepository(SubscriptionTypesRepository::class);
        $this->userManager = $this->inject(UserManager::class);
        $this->subscriptionShortenedHandler = $this->inject(SubscriptionShortenedHandler::class);
    }

    public function testNoAction()
    {
        $user = $this->loadUser('admin@example.com');
        $baseSubscription = $this->createSubscription($user, TestSeeder::SUBSCRIPTION_TYPE_WEB_YEAR, new \DateTime('2019-01-01'));

        $endTime = new \DateTime('2019-07-01');
        $upgradedSubscription = $this->createSubscription($user, TestSeeder::SUBSCRIPTION_TYPE_WEB_MONTH, $endTime);
        $this->subscriptionsRepository->update($baseSubscription, [
            'end_time' => $endTime,
        ]);
        $this->subscriptionShortenedHandler->handle(
            new SubscriptionShortenedEvent($baseSubscription, new \DateTime('2020-01-01')),
        );

        $subscriptions = [];
        foreach ($this->subscriptionsRepository->userSubscriptions($user->id) as $s) {
            $subscriptions[] = $s;
        }
        $this->assertCount(2, $subscriptions);
        $this->assertEquals(new \DateTime('2019-01-01'), $subscriptions[1]->start_time);
        $this->assertEquals(new \DateTime('2019-07-01'), $subscriptions[1]->end_time);
        $this->assertEquals(new \DateTime('2019-07-01'), $subscriptions[0]->start_time);
        $this->assertEquals(new \DateTime('2019-08-01'), $subscriptions[0]->end_time);
    }

    public function testShortFirstHandleMoveOfSecond()
    {
        $user = $this->loadUser('admin@example.com');
        $subscription1 = $this->createSubscription($user, TestSeeder::SUBSCRIPTION_TYPE_WEB_YEAR, new \DateTime('2019-01-01'));
        $subscription2 = $this->createSubscription($user, TestSeeder::SUBSCRIPTION_TYPE_WEB_YEAR, $subscription1->end_time);

        $endTime = new \DateTime('2019-07-01');
        $upgradedSubscription = $this->createSubscription($user, TestSeeder::SUBSCRIPTION_TYPE_WEB_MONTH, $endTime);
        $this->subscriptionsRepository->update($subscription1, [
            'end_time' => $endTime,
        ]);
        $this->subscriptionShortenedHandler->handle(
            new SubscriptionShortenedEvent($subscription1, new \DateTime('2020-01-01')),
        );

        $subscriptions = [];
        foreach ($this->subscriptionsRepository->userSubscriptions($user->id) as $s) {
            $subscriptions[] = $s;
        }
        $this->assertCount(3, $subscriptions);
        $this->assertEquals(new \DateTime('2019-01-01'), $subscriptions[2]->start_time);
        $this->assertEquals(new \DateTime('2019-07-01'), $subscriptions[2]->end_time);
        $this->assertEquals(new \DateTime('2019-07-01'), $subscriptions[1]->start_time);
        $this->assertEquals(new \DateTime('2019-08-01'), $subscriptions[1]->end_time);
        $this->assertEquals(new \DateTime('2019-08-01'), $subscriptions[0]->start_time);
        $this->assertEquals(new \DateTime('2020-07-31'), $subscriptions[0]->end_time); // leap year
    }

    public function testMutlipleUpgradedSubscriptions()
    {
        $user = $this->loadUser('admin@example.com');
        $subscription1 = $this->createSubscription($user, TestSeeder::SUBSCRIPTION_TYPE_WEB_YEAR, new \DateTime('2019-01-01'));
        $subscription2 = $this->createSubscription($user, TestSeeder::SUBSCRIPTION_TYPE_WEB_YEAR, $subscription1->end_time);

        $endTime = new \DateTime('2019-07-01');

        $upgradedSubscription1= $this->createSubscription($user, TestSeeder::SUBSCRIPTION_TYPE_WEB_MONTH, $endTime);
        $upgradedSubscription2= $this->createSubscription($user, TestSeeder::SUBSCRIPTION_TYPE_WEB_MONTH, $upgradedSubscription1->end_time);

        $this->subscriptionsRepository->update($subscription1, [
            'end_time' => $endTime,
        ]);

        $this->subscriptionShortenedHandler->handle(
            new SubscriptionShortenedEvent($subscription1, new \DateTime('2020-01-01')),
        );

        $subscriptions = [];
        foreach ($this->subscriptionsRepository->userSubscriptions($user->id) as $s) {
            $subscriptions[] = $s;
        }
        $this->assertCount(4, $subscriptions);
        $this->assertEquals(new \DateTime('2019-01-01'), $subscriptions[3]->start_time); // $subscription1
        $this->assertEquals(new \DateTime('2019-07-01'), $subscriptions[3]->end_time);
        $this->assertEquals(new \DateTime('2019-07-01'), $subscriptions[2]->start_time); // $upgradedSubscription1
        $this->assertEquals(new \DateTime('2019-08-01'), $subscriptions[2]->end_time);
        $this->assertEquals(new \DateTime('2019-08-01'), $subscriptions[1]->start_time); // $upgradedSubscription2
        $this->assertEquals(new \DateTime('2019-09-01'), $subscriptions[1]->end_time);
        $this->assertEquals(new \DateTime('2019-09-01'), $subscriptions[0]->start_time); // $subscription2
        $this->assertEquals(new \DateTime('2020-08-31'), $subscriptions[0]->end_time);
    }

    public function testShortFirstHandleMoveOfSecondAndThird()
    {
        $user = $this->loadUser('admin@example.com');
        $subscription1 = $this->createSubscription($user, TestSeeder::SUBSCRIPTION_TYPE_WEB_YEAR, new \DateTime('2019-01-01'));
        $subscription2 = $this->createSubscription($user, TestSeeder::SUBSCRIPTION_TYPE_WEB_YEAR, $subscription1->end_time);
        $subscription3 = $this->createSubscription($user, TestSeeder::SUBSCRIPTION_TYPE_WEB_YEAR, $subscription2->end_time);

        $endTime = new \DateTime('2019-07-01');
        $upgradedSubscription = $this->createSubscription($user, TestSeeder::SUBSCRIPTION_TYPE_WEB_MONTH, $endTime);
        $this->subscriptionsRepository->update($subscription1, [
            'end_time' => $endTime,
        ]);
        $this->subscriptionShortenedHandler->handle(
            new SubscriptionShortenedEvent($subscription1, new \DateTime('2020-01-01')),
        );

        $subscriptions = [];
        foreach ($this->subscriptionsRepository->userSubscriptions($user->id) as $s) {
            $subscriptions[] = $s;
        }
        $this->assertCount(4, $subscriptions);
        $this->assertEquals(new \DateTime('2019-01-01'), $subscriptions[3]->start_time);
        $this->assertEquals(new \DateTime('2019-07-01'), $subscriptions[3]->end_time);
        $this->assertEquals(new \DateTime('2019-07-01'), $subscriptions[2]->start_time);
        $this->assertEquals(new \DateTime('2019-08-01'), $subscriptions[2]->end_time);
        $this->assertEquals(new \DateTime('2019-08-01'), $subscriptions[1]->start_time);
        $this->assertEquals(new \DateTime('2020-07-31'), $subscriptions[1]->end_time); // 2020 is leap year
        $this->assertEquals(new \DateTime('2020-07-31'), $subscriptions[0]->start_time);
        $this->assertEquals(new \DateTime('2021-07-31'), $subscriptions[0]->end_time);
    }

    public function testIgnoreOfParallelSubscription()
    {
        $user = $this->loadUser('admin@example.com');
        $subscription1 = $this->createSubscription($user, TestSeeder::SUBSCRIPTION_TYPE_WEB_YEAR, new \DateTime('2019-01-01'));
        $parallelSubscription = $this->createSubscription($user, TestSeeder::SUBSCRIPTION_TYPE_WEB_YEAR, new \DateTime('2019-06-15'), new \DateTime('2019-07-15'));
        $subscription2 = $this->createSubscription($user, TestSeeder::SUBSCRIPTION_TYPE_WEB_YEAR, $subscription1->end_time);

        $endTime = new \DateTime('2019-07-01');
        $upgradedSubscription = $this->createSubscription($user, TestSeeder::SUBSCRIPTION_TYPE_WEB_MONTH, $endTime);
        $this->subscriptionsRepository->update($subscription1, [
            'end_time' => $endTime,
        ]);
        $this->subscriptionShortenedHandler->handle(
            new SubscriptionShortenedEvent($subscription1, new \DateTime('2020-01-01')),
        );

        $subscriptions = [];
        foreach ($this->subscriptionsRepository->userSubscriptions($user->id) as $s) {
            $subscriptions[] = $s;
        }
        $this->assertCount(4, $subscriptions);
        $this->assertEquals(new \DateTime('2019-01-01'), $subscriptions[3]->start_time);
        $this->assertEquals(new \DateTime('2019-07-01'), $subscriptions[3]->end_time);
        $this->assertEquals(new \DateTime('2019-06-15'), $subscriptions[2]->start_time);
        $this->assertEquals(new \DateTime('2019-07-15'), $subscriptions[2]->end_time);
        $this->assertEquals(new \DateTime('2019-07-01'), $subscriptions[1]->start_time);
        $this->assertEquals(new \DateTime('2019-08-01'), $subscriptions[1]->end_time);
        $this->assertEquals(new \DateTime('2019-08-01'), $subscriptions[0]->start_time);
        $this->assertEquals(new \DateTime('2020-07-31'), $subscriptions[0]->end_time); // 2020 is leap year
    }

    public function testDifferentUserNotAffected()
    {
        $user1 = $this->loadUser('admin@example.com');
        $user2 = $this->loadUser('user@example.com');

        $subscription1 = $this->createSubscription($user1, TestSeeder::SUBSCRIPTION_TYPE_WEB_YEAR, new \DateTime('2019-01-01'));
        $subscription2 = $this->createSubscription($user2, TestSeeder::SUBSCRIPTION_TYPE_WEB_YEAR, $subscription1->end_time);

        $endTime = new \DateTime('2019-07-01');
        $upgradedSubscription = $this->createSubscription($user1, TestSeeder::SUBSCRIPTION_TYPE_WEB_MONTH, $endTime);
        $this->subscriptionsRepository->update($subscription1, [
            'end_time' => $endTime,
        ]);
        $this->subscriptionShortenedHandler->handle(
            new SubscriptionShortenedEvent($subscription1, new \DateTime('2020-01-01')),
        );

        $subscriptions = [];
        foreach ($this->subscriptionsRepository->userSubscriptions($user1->id) as $s) {
            $subscriptions[] = $s;
        }
        $this->assertCount(2, $subscriptions);
        $this->assertEquals(new \DateTime('2019-01-01'), $subscriptions[1]->start_time);
        $this->assertEquals(new \DateTime('2019-07-01'), $subscriptions[1]->end_time);
        $this->assertEquals(new \DateTime('2019-07-01'), $subscriptions[0]->start_time);
        $this->assertEquals(new \DateTime('2019-08-01'), $subscriptions[0]->end_time);

        $subscriptions = [];
        foreach ($this->subscriptionsRepository->userSubscriptions($user2->id) as $s) {
            $subscriptions[] = $s;
        }
        $this->assertEquals(new \DateTime('2020-01-01'), $subscriptions[0]->start_time);
        $this->assertEquals(new \DateTime('2020-12-31'), $subscriptions[0]->end_time); // 2020 is leap year
    }

    public function testStopSubscriptionNoAction()
    {
        $user = $this->loadUser('admin@example.com');

        $subscription1 = $this->createSubscription($user, TestSeeder::SUBSCRIPTION_TYPE_WEB_YEAR, new \DateTime('2019-01-01'));
        $originalEndTime = $subscription1->end_time;

        $endTime = new \DateTime('2019-07-01');
        $this->subscriptionsRepository->update($subscription1, [
            'end_time' => $endTime,
        ]);
        $this->subscriptionShortenedHandler->handle(
            new SubscriptionShortenedEvent($subscription1, $originalEndTime),
        );

        $subscriptions = [];
        foreach ($this->subscriptionsRepository->userSubscriptions($user->id) as $s) {
            $subscriptions[] = $s;
        }
        $this->assertCount(1, $subscriptions);
        $this->assertEquals(new \DateTime('2019-01-01'), $subscriptions[0]->start_time);
        $this->assertEquals(new \DateTime('2019-07-01'), $subscriptions[0]->end_time);
    }

    public function testStopSubscriptionWithOneFollowingSubscription()
    {
        $user = $this->loadUser('admin@example.com');

        $subscription1 = $this->createSubscription($user, TestSeeder::SUBSCRIPTION_TYPE_WEB_YEAR, new \DateTime('2019-01-01'));
        $subscription2 = $this->createSubscription($user, TestSeeder::SUBSCRIPTION_TYPE_WEB_YEAR, $subscription1->end_time);

        $originalEndTime = $subscription1->end_time;

        $endTime = new \DateTime('2019-07-01');
        $this->subscriptionsRepository->update($subscription1, [
            'end_time' => $endTime,
        ]);
        $this->subscriptionShortenedHandler->handle(
            new SubscriptionShortenedEvent($subscription1, $originalEndTime),
        );

        $subscriptions = [];
        foreach ($this->subscriptionsRepository->userSubscriptions($user->id) as $s) {
            $subscriptions[] = $s;
        }
        $this->assertCount(2, $subscriptions);
        $this->assertEquals(new \DateTime('2019-01-01'), $subscriptions[1]->start_time);
        $this->assertEquals(new \DateTime('2019-07-01'), $subscriptions[1]->end_time);
        $this->assertEquals(new \DateTime('2019-07-01'), $subscriptions[0]->start_time);
        $this->assertEquals(new \DateTime('2020-06-30'), $subscriptions[0]->end_time); // 2020 is leap year
    }

    public function testStopSecondSubscriptionWithMultipleFollowingSubscriptions()
    {
        $user = $this->loadUser('admin@example.com');

        $subscription1 = $this->createSubscription($user, TestSeeder::SUBSCRIPTION_TYPE_WEB_YEAR, new \DateTime('2021-01-01'));
        $subscription2 = $this->createSubscription($user, TestSeeder::SUBSCRIPTION_TYPE_WEB_YEAR, $subscription1->end_time);
        $subscription3 = $this->createSubscription($user, TestSeeder::SUBSCRIPTION_TYPE_WEB_MONTH, $subscription2->end_time);
        $subscription4 = $this->createSubscription($user, TestSeeder::SUBSCRIPTION_TYPE_WEB_MONTH, $subscription3->end_time);

        $originalEndTime = $subscription2->end_time;

        $endTime = new \DateTime('2022-07-01');
        $this->subscriptionsRepository->update($subscription2, [
            'end_time' => $endTime,
        ]);
        $this->subscriptionShortenedHandler->handle(
            new SubscriptionShortenedEvent($subscription1, $originalEndTime),
        );

        $subscriptions = [];
        foreach ($this->subscriptionsRepository->userSubscriptions($user->id) as $s) {
            $subscriptions[] = $s;
        }
        $this->assertCount(4, $subscriptions);
        $this->assertEquals(new \DateTime('2021-01-01'), $subscriptions[3]->start_time);
        $this->assertEquals(new \DateTime('2022-01-01'), $subscriptions[3]->end_time);
        $this->assertEquals(new \DateTime('2022-01-01'), $subscriptions[2]->start_time);
        $this->assertEquals(new \DateTime('2022-07-01'), $subscriptions[2]->end_time);
        $this->assertEquals(new \DateTime('2022-07-01'), $subscriptions[1]->start_time);
        $this->assertEquals(new \DateTime('2022-08-01'), $subscriptions[1]->end_time);
        $this->assertEquals(new \DateTime('2022-08-01'), $subscriptions[0]->start_time);
        $this->assertEquals(new \DateTime('2022-09-01'), $subscriptions[0]->end_time);
    }

    public function testShorteningDuringUpgrade()
    {
        $user = $this->loadUser('admin@example.com');

        $subscription1 = $this->createSubscription($user, TestSeeder::SUBSCRIPTION_TYPE_WEB_MONTH, new \DateTime('2021-01-01'), new \DateTime('2021-02-01'));
        $subscription2 = $this->createSubscription($user, TestSeeder::SUBSCRIPTION_TYPE_WEB_MONTH, new \DateTime('2021-02-01'), new \DateTime('2021-03-01'));

        // simulate upgrade before end of subscription 1
        $this->subscriptionsRepository->update($subscription1, [
            'end_time' => new \DateTime('2021-01-31'),
        ]);
        $subscription3 = $this->createSubscription($user, TestSeeder::SUBSCRIPTION_TYPE_WEB_MONTH, new \DateTime('2021-01-31'), new \DateTime('2021-02-01'));

        // emit the event, that the original subscription was shortened
        $this->subscriptionShortenedHandler->handle(
            new SubscriptionShortenedEvent($subscription1, new \DateTime('2021-02-01')),
        );

        $subscriptions = [];
        foreach ($this->subscriptionsRepository->userSubscriptions($user->id) as $s) {
            $subscriptions[] = $s;
        }

        $this->assertCount(3, $subscriptions);
        $this->assertEquals(new \DateTime('2021-01-01'), $subscriptions[2]->start_time);
        $this->assertEquals(new \DateTime('2021-01-31'), $subscriptions[2]->end_time);
        $this->assertEquals(new \DateTime('2021-01-31'), $subscriptions[1]->start_time);
        $this->assertEquals(new \DateTime('2021-02-01'), $subscriptions[1]->end_time);
        $this->assertEquals(new \DateTime('2021-02-01'), $subscriptions[0]->start_time);
        $this->assertEquals(new \DateTime('2021-03-01'), $subscriptions[0]->end_time);
    }

    public function testStopSubscriptionSameStartAndEnd()
    {
        $user = $this->loadUser('admin@example.com');

        $subscription1 = $this->createSubscription($user, TestSeeder::SUBSCRIPTION_TYPE_WEB_YEAR, new \DateTime('2021-01-01'), new \DateTime('2021-01-01'));
        $originalEndTime = $subscription1->end_time;
        $originalModifiedTime = $subscription1->modified_at;

        $this->subscriptionShortenedHandler->handle(
            new SubscriptionShortenedEvent($subscription1, $originalEndTime),
        );

        $subscriptions = [];
        foreach ($this->subscriptionsRepository->userSubscriptions($user->id) as $s) {
            $subscriptions[] = $s;
        }
        $this->assertCount(1, $subscriptions);
        $this->assertEquals(new \DateTime('2021-01-01'), $subscriptions[0]->start_time);
        $this->assertEquals(new \DateTime('2021-01-01'), $subscriptions[0]->end_time);
        $this->assertEquals($originalModifiedTime, $subscriptions[0]->modified_at);
        $this->assertNull($subscriptions[0]->note);
    }

    private function createSubscription(ActiveRow $user, string $code, \DateTime $startTime, \DateTime $endTime = null)
    {
        $st = $this->subscriptionTypesRepository->findByCode($code);
        return $this->subscriptionsRepository->add(
            $st,
            false,
            true,
            $user,
            SubscriptionsRepository::TYPE_REGULAR,
            $startTime,
            $endTime,
        );
    }

    private function loadUser($email) : ActiveRow
    {
        $user = $this->userManager->loadUserByEmail($email);
        if (!$user) {
            $user = $this->userManager->addNewUser($email);
        }
        return $user;
    }
}
