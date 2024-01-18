<?php

namespace Crm\SubscriptionsModule\Tests;

use Crm\ApplicationModule\Tests\DatabaseTestCase;
use Crm\SubscriptionsModule\Models\Builder\SubscriptionTypeBuilder;
use Crm\SubscriptionsModule\Repositories\SubscriptionTypesRepository;
use Crm\SubscriptionsModule\Repositories\SubscriptionsRepository;
use Crm\SubscriptionsModule\Seeders\SubscriptionExtensionMethodsSeeder;
use Crm\SubscriptionsModule\Seeders\SubscriptionLengthMethodSeeder;
use Crm\SubscriptionsModule\Seeders\SubscriptionTypeNamesSeeder;
use Crm\UsersModule\Auth\UserManager;
use Crm\UsersModule\Repository\UsersRepository;
use Nette\Database\Table\ActiveRow;
use Nette\Utils\DateTime;

class NextSubscriptionIdTest extends DatabaseTestCase
{
    /** @var SubscriptionsRepository */
    private $subscriptionsRepository;

    private $user;

    public function setUp(): void
    {
        parent::setUp();

        $this->subscriptionsRepository = $this->getRepository(SubscriptionsRepository::class);

        $userManager = $this->inject(UserManager::class);
        $this->user = $userManager->addNewUser('test@example.com');
    }

    protected function requiredRepositories(): array
    {
        return [
            SubscriptionsRepository::class,
            SubscriptionTypesRepository::class,
            UsersRepository::class,
        ];
    }

    protected function requiredSeeders(): array
    {
        return [
            SubscriptionTypeNamesSeeder::class,
            SubscriptionExtensionMethodsSeeder::class,
            SubscriptionLengthMethodSeeder::class
        ];
    }

    private function getSubscriptionType()
    {
        /** @var SubscriptionTypeBuilder $subscriptionTypeBuilder */
        $subscriptionTypeBuilder = $this->inject(SubscriptionTypeBuilder::class);

        $subscriptionTypeRow = $subscriptionTypeBuilder
            ->createNew()
            ->setNameAndUserLabel(random_int(0, 9999))
            ->setActive(1)
            ->setPrice(1)
            ->setLength(30)
            ->save();

        return $subscriptionTypeRow;
    }

    private function addSubscription(ActiveRow $subscriptionType, DateTime $from = null, DateTime $to = null): ActiveRow
    {
        return $this->subscriptionsRepository->add(
            $subscriptionType,
            false,
            true,
            $this->user,
            SubscriptionsRepository::TYPE_REGULAR,
            $from,
            $to
        );
    }

    public function testAddSecondSubscriptionFollowing()
    {
        // SET UP -------------------------------------------------------------

        $subscriptionType = $this->getSubscriptionType();
        $firstSubscription = $this->addSubscription($subscriptionType);
        $this->assertNull($firstSubscription->next_subscription_id);
        // if secondSubscription.start_time is same as firstSubscription.end_time,
        // firstSubscription.next_subscription_id is automatically set to secondSubscription.id
        $startTime = $firstSubscription->end_time;
        $secondSubscription = $this->addSubscription($subscriptionType, $startTime);
        // reload; to be sure we have current data
        $firstSubscription = $this->subscriptionsRepository->find($firstSubscription);

        // TEST -------------------------------------------------------------

        // check; subscriptions should be connected
        $this->assertEquals($secondSubscription->id, $firstSubscription->next_subscription_id);
    }

    public function testAddSecondSubscriptionWithGap()
    {
        // SET UP -------------------------------------------------------------

        $subscriptionType = $this->getSubscriptionType();
        $firstSubscription = $this->addSubscription($subscriptionType);
        $this->assertNull($firstSubscription->next_subscription_id);
        // if secondSubscription.start_time is NOT same as firstSubscription.end_time, firstSubscription.next_subscription_id is NOT filled
        $startTime = $firstSubscription->end_time->modifyClone('+2 hours');
        $secondSubscription = $this->addSubscription($subscriptionType, $startTime);
        // reload; to be sure we have current data
        $firstSubscription = $this->subscriptionsRepository->find($firstSubscription);

        // TEST -------------------------------------------------------------

        // check; subscriptions shouldn't be connected
        $this->assertNull($firstSubscription->next_subscription_id);
    }

    public function testChangeStartTimeOfSecondSubscriptionToNotFollow()
    {
        // SET UP -------------------------------------------------------------

        // add two following subscriptions
        $subscriptionType = $this->getSubscriptionType();
        $firstSubscription = $this->addSubscription($subscriptionType);
        $this->assertNull($firstSubscription->next_subscription_id);
        // if secondSubscription.start_time is same as firstSubscription.end_time,
        // firstSubscription.next_subscription_id is automatically set to secondSubscription.id
        $startTime = $firstSubscription->end_time;
        $secondSubscription = $this->addSubscription($subscriptionType, $startTime);
        // reload; to be sure we have current data
        $firstSubscription = $this->subscriptionsRepository->find($firstSubscription);
        // check state
        $this->assertEquals($secondSubscription->id, $firstSubscription->next_subscription_id);

        // TEST -------------------------------------------------------------

        // move start of secondSubscription; creates gap
        $this->subscriptionsRepository->update(
            $secondSubscription,
            [
                'start_time' => $secondSubscription->start_time->modifyClone('+2 hours'),
                'end_time' => $secondSubscription->end_time->modifyClone('+2 hours'),
            ]
        );

        // check if firstSubscription.next_subscription_id was removed
        $firstSubscription = $this->subscriptionsRepository->find($firstSubscription);
        $this->assertNull($firstSubscription->next_subscription_id);
    }

    public function testChangeEndTimeOfFirstSubscriptionToNotFollow()
    {
        // SET UP -------------------------------------------------------------

        // add two following subscriptions
        $subscriptionType = $this->getSubscriptionType();
        $firstSubscription = $this->addSubscription($subscriptionType);
        $this->assertNull($firstSubscription->next_subscription_id);
        // if secondSubscription.start_time is same as firstSubscription.end_time,
        // firstSubscription.next_subscription_id is automatically set to secondSubscription.id
        $startTime = $firstSubscription->end_time;
        $secondSubscription = $this->addSubscription($subscriptionType, $startTime);
        // reload; to be sure we have current data
        $firstSubscription = $this->subscriptionsRepository->find($firstSubscription);
        // check state
        $this->assertEquals($secondSubscription->id, $firstSubscription->next_subscription_id);

        // TEST -------------------------------------------------------------

        // move end of firstSubscription; creates gap
        $this->subscriptionsRepository->update(
            $firstSubscription,
            [
                'start_time' => $firstSubscription->start_time->modifyClone('-2 hours'),
                'end_time' => $firstSubscription->end_time->modifyClone('-2 hours'),
            ]
        );

        // check if firstSubscription.next_subscription_id was removed
        $firstSubscription = $this->subscriptionsRepository->find($firstSubscription);
        $this->assertNull($firstSubscription->next_subscription_id);
    }

    public function testChangeStartTimeOfSecondSubscriptionToFollow()
    {
        // SET UP -------------------------------------------------------------

        $subscriptionType = $this->getSubscriptionType();
        $firstSubscription = $this->addSubscription($subscriptionType);
        $this->assertNull($firstSubscription->next_subscription_id);
        // if secondSubscription.start_time is NOT same as firstSubscription.end_time, firstSubscription.next_subscription_id is NOT filled
        $startTime = $firstSubscription->end_time->modifyClone('+2 hours');
        $secondSubscription = $this->addSubscription($subscriptionType, $startTime);
        // reload; to be sure we have current data
        $firstSubscription = $this->subscriptionsRepository->find($firstSubscription);
        // check state
        $this->assertNull($firstSubscription->next_subscription_id);

        // TEST ---------------------------------------------------------------

        // move start of secondSubscription to end of firstSubscription; removes gap; should be following
        $this->subscriptionsRepository->update(
            $secondSubscription,
            [
                'start_time' => $firstSubscription->end_time,
                'end_time' => $secondSubscription->end_time->modifyClone('-2 hours'),
            ]
        );

        // check if firstSubscription.next_subscription_id was set
        $firstSubscription = $this->subscriptionsRepository->find($firstSubscription);
        $this->assertEquals($secondSubscription->id, $firstSubscription->next_subscription_id);
    }

    public function testChangeEndTimeOfFirstSubscriptionToFollow()
    {
        // SET UP -------------------------------------------------------------

        $subscriptionType = $this->getSubscriptionType();
        $firstSubscription = $this->addSubscription($subscriptionType);
        $this->assertNull($firstSubscription->next_subscription_id);
        // if secondSubscription.start_time is NOT same as firstSubscription.end_time, firstSubscription.next_subscription_id is NOT filled
        $startTime = $firstSubscription->end_time->modifyClone('+2 hours');
        $secondSubscription = $this->addSubscription($subscriptionType, $startTime);
        // reload; to be sure we have current data
        $firstSubscription = $this->subscriptionsRepository->find($firstSubscription);
        // check state
        $this->assertNull($firstSubscription->next_subscription_id);

        // TEST ---------------------------------------------------------------

        // move end of firstSubscription to start of secondSubscription; removes gap; should be following
        $this->subscriptionsRepository->update(
            $firstSubscription,
            [
                'start_time' => $firstSubscription->start_time->modifyClone('-2 hours'),
                'end_time' => $secondSubscription->start_time,
            ]
        );

        // check if firstSubscription.next_subscription_id was set
        $firstSubscription = $this->subscriptionsRepository->find($firstSubscription);
        $this->assertEquals($secondSubscription->id, $firstSubscription->next_subscription_id);
    }
}
