<?php

namespace Crm\SubscriptionsModule\Tests;

use Crm\ApplicationModule\Tests\DatabaseTestCase;
use Crm\SubscriptionsModule\Models\Builder\SubscriptionTypeBuilder;
use Crm\SubscriptionsModule\Models\Extension\ExtendActualExtension;
use Crm\SubscriptionsModule\Repositories\SubscriptionExtensionMethodsRepository;
use Crm\SubscriptionsModule\Repositories\SubscriptionLengthMethodsRepository;
use Crm\SubscriptionsModule\Repositories\SubscriptionTypeContentAccessRepository;
use Crm\SubscriptionsModule\Repositories\SubscriptionTypeNamesRepository;
use Crm\SubscriptionsModule\Repositories\SubscriptionTypesRepository;
use Crm\SubscriptionsModule\Repositories\SubscriptionsRepository;
use Crm\SubscriptionsModule\Seeders\SubscriptionExtensionMethodsSeeder;
use Crm\SubscriptionsModule\Seeders\SubscriptionLengthMethodSeeder;
use Crm\SubscriptionsModule\Seeders\SubscriptionTypeNamesSeeder;
use Crm\UsersModule\Models\Auth\UserManager;
use Crm\UsersModule\Repositories\UsersRepository;
use Nette\Database\Table\ActiveRow;
use Nette\Utils\DateTime;

class ExtendActualExtensionTest extends DatabaseTestCase
{
    private SubscriptionsRepository $subscriptionsRepository;

    private ExtendActualExtension $extension;

    private ActiveRow $user;

    public function setUp(): void
    {
        parent::setUp();

        $this->extension = $this->inject(ExtendActualExtension::class);
        $this->subscriptionsRepository = $this->getRepository(SubscriptionsRepository::class);
        /** @var UserManager $userManager */
        $userManager = $this->inject(UserManager::class);
        $this->user = $userManager->addNewUser('test@example.com');
    }

    public function tearDown(): void
    {
        // reset NOW; it affects tests run after this class
        $this->extension->setNow(null);
        $this->subscriptionsRepository->setNow(null);

        parent::tearDown();
    }

    protected function requiredRepositories(): array
    {
        return [
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
            SubscriptionExtensionMethodsSeeder::class,
            SubscriptionLengthMethodSeeder::class,
            SubscriptionTypeNamesSeeder::class,
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

    private function addSubscription(ActiveRow $subscriptionType, DateTime $from, DateTime $to)
    {
        $this->subscriptionsRepository->add(
            $subscriptionType,
            false,
            true,
            $this->user,
            SubscriptionsRepository::TYPE_REGULAR,
            $from,
            $to,
        );
    }

    public function testNoSubscription()
    {
        $nowDate = DateTime::from('2021-02-01');
        $this->extension->setNow($nowDate);
        $this->subscriptionsRepository->setNow($nowDate);

        $subscriptionType = $this->getSubscriptionType();

        $result = $this->extension->getStartTime($this->user, $subscriptionType);

        $this->assertEquals($nowDate, $result->getDate());
        $this->assertFalse($result->isExtending());
    }

    public function testActualSubscription()
    {
        $nowDate = DateTime::from('2021-02-01');
        $this->extension->setNow($nowDate);
        $this->subscriptionsRepository->setNow($nowDate);

        $subscriptionType = $this->getSubscriptionType();
        $this->addSubscription($subscriptionType, $nowDate->modifyClone('-5 days'), $nowDate->modifyClone('+25 days'));

        $result = $this->extension->getStartTime($this->user, $subscriptionType);

        $this->assertEquals($nowDate->modifyClone('+25 days'), $result->getDate());
        $this->assertTrue($result->isExtending());
    }

    public function testExpiredSubscription()
    {
        $nowDate = DateTime::from('2021-02-01');
        $this->extension->setNow($nowDate);
        $this->subscriptionsRepository->setNow($nowDate);

        $subscriptionType = $this->getSubscriptionType();
        $this->addSubscription($subscriptionType, $nowDate->modifyClone('-35 days'), $nowDate->modifyClone('-5 days'));

        $result = $this->extension->getStartTime($this->user, $subscriptionType);

        $this->assertEquals($nowDate, $result->getDate());
        $this->assertFalse($result->isExtending());
    }

    public function testLastActualSubscription()
    {
        $nowDate = DateTime::from('2021-02-01');
        $this->extension->setNow($nowDate);
        $this->subscriptionsRepository->setNow($nowDate);

        $subscriptionType = $this->getSubscriptionType();
        $this->addSubscription($subscriptionType, $nowDate->modifyClone('-5 days'), $nowDate->modifyClone('+25 days'));
        $this->addSubscription($subscriptionType, $nowDate->modifyClone('-10 days'), $nowDate->modifyClone('+20 days'));
        // this subscription will be ignored; it is not actual (currently running)
        $this->addSubscription($subscriptionType, $nowDate->modifyClone('+180 days'), $nowDate->modifyClone('+210 days'));

        $result = $this->extension->getStartTime($this->user, $subscriptionType);

        $this->assertEquals($nowDate->modifyClone('+25 days'), $result->getDate());
        $this->assertTrue($result->isExtending());
    }
}
