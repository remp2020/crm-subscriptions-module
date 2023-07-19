<?php

namespace Crm\SubscriptionsModule\Tests;

use Crm\ApplicationModule\DataProvider\DataProviderException;
use Crm\ApplicationModule\Tests\DatabaseTestCase;
use Crm\SubscriptionsModule\Builder\SubscriptionTypeBuilder;
use Crm\SubscriptionsModule\DataProvider\SubscriptionsClaimUserDataProvider;
use Crm\SubscriptionsModule\Repository\SubscriptionTypeItemsRepository;
use Crm\SubscriptionsModule\Repository\SubscriptionTypesRepository;
use Crm\SubscriptionsModule\Repository\SubscriptionsRepository;
use Crm\SubscriptionsModule\Seeders\SubscriptionExtensionMethodsSeeder;
use Crm\SubscriptionsModule\Seeders\SubscriptionLengthMethodSeeder;
use Crm\SubscriptionsModule\Seeders\SubscriptionTypeNamesSeeder;
use Crm\UsersModule\Repository\UserMetaRepository;
use Crm\UsersModule\Repository\UsersRepository;
use Crm\UsersModule\Seeders\UsersSeeder;
use Crm\UsersModule\User\UnclaimedUser;

class SubscriptionsClaimUserDataProviderTest extends DatabaseTestCase
{
    private $dataProvider;

    private $subscriptionType;

    /** @var SubscriptionsRepository */
    private $subscriptionsRepository;

    /** @var SubscriptionTypeBuilder */
    private $subscriptionTypeBuilder;

    /** @var UsersRepository */
    private $usersRepository;

    /** @var UnclaimedUser */
    private $unclaimedUser;

    private $unclaimedUserObj;

    private $loggedUser;

    protected function requiredRepositories(): array
    {
        return [
            SubscriptionsRepository::class,
            SubscriptionTypesRepository::class,
            SubscriptionTypeItemsRepository::class,
            UsersRepository::class,
            UserMetaRepository::class
        ];
    }

    protected function requiredSeeders(): array
    {
        return [
            UsersSeeder::class,
            SubscriptionExtensionMethodsSeeder::class,
            SubscriptionLengthMethodSeeder::class,
            SubscriptionTypeNamesSeeder::class,
        ];
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->dataProvider = $this->inject(SubscriptionsClaimUserDataProvider::class);

        $this->subscriptionsRepository = $this->getRepository(SubscriptionsRepository::class);
        $this->unclaimedUser = $this->inject(UnclaimedUser::class);
        $this->usersRepository = $this->getRepository(UsersRepository::class);

        $this->subscriptionTypeBuilder = $this->inject(SubscriptionTypeBuilder::class);
        $this->subscriptionType = $this->subscriptionTypeBuilder->createNew()
            ->setNameAndUserLabel('Online mesiac - iba web')
            ->setCode('web_month')
            ->setPrice(4.9)
            ->setLength(31)
            ->setSorting(10)
            ->setActive(true)
            ->setVisible(true)
            ->setDescription('web')
            ->setContentAccessOption('web')
            ->save();

        $this->unclaimedUserObj = $this->unclaimedUser->createUnclaimedUser();
        $this->loggedUser = $this->usersRepository->getByEmail(UsersSeeder::USER_ADMIN);
    }

    public function testWrongArguments(): void
    {
        $this->expectException(DataProviderException::class);
        $this->dataProvider->provide([]);
    }

    public function testClaimUserSubscriptions(): void
    {
        $subscription = $this->subscriptionsRepository->add($this->subscriptionType, false, false, $this->unclaimedUserObj);

        $this->dataProvider->provide(['unclaimedUser' => $this->unclaimedUserObj, 'loggedUser' => $this->loggedUser]);

        $this->assertEmpty($this->subscriptionsRepository->userSubscriptions($this->unclaimedUserObj->id)->fetchAll());

        $loggedUserSubscriptions = $this->subscriptionsRepository->userSubscriptions($this->loggedUser->id);
        $this->assertCount(1, $loggedUserSubscriptions->fetchAll());
        $this->assertEquals($subscription->id, $loggedUserSubscriptions->fetch()->id);
    }
}
