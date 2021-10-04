<?php

namespace Crm\SubscriptionsModule\Tests;

use Crm\ApplicationModule\Tests\DatabaseTestCase;
use Crm\SubscriptionsModule\Builder\SubscriptionTypeBuilder;
use Crm\SubscriptionsModule\Extension\ExtendSameContentAccess;
use Crm\SubscriptionsModule\Repository\ContentAccessRepository;
use Crm\SubscriptionsModule\Repository\SubscriptionExtensionMethodsRepository;
use Crm\SubscriptionsModule\Repository\SubscriptionsRepository;
use Crm\SubscriptionsModule\Repository\SubscriptionTypeNamesRepository;
use Crm\SubscriptionsModule\Repository\SubscriptionTypesRepository;
use Crm\SubscriptionsModule\Seeders\ContentAccessSeeder;
use Crm\SubscriptionsModule\Seeders\SubscriptionExtensionMethodsSeeder;
use Crm\SubscriptionsModule\Seeders\SubscriptionLengthMethodSeeder;
use Crm\SubscriptionsModule\Seeders\SubscriptionTypeNamesSeeder;
use Crm\UsersModule\Auth\UserManager;
use Crm\UsersModule\Repository\UsersRepository;
use Nette\Database\Table\IRow;
use Nette\Utils\DateTime;

class ExtendSameContentAccessExtensionTest extends DatabaseTestCase
{
    /** @var SubscriptionsRepository */
    private $subscriptionsRepository;

    /** @var ExtendSameContentAccess */
    private $extension;

    private $user;

    public function setUp(): void
    {
        parent::setUp();

        $this->extension = $this->inject(ExtendSameContentAccess::class);
        $this->subscriptionsRepository = $this->getRepository(SubscriptionsRepository::class);
        $userManager = $this->inject(UserManager::class);
        $this->user = $userManager->addNewUser('test@example.com');
    }

    protected function requiredRepositories(): array
    {
        return [
            SubscriptionsRepository::class,
            SubscriptionTypesRepository::class,
            SubscriptionTypeNamesRepository::class,
            SubscriptionExtensionMethodsRepository::class,
            UsersRepository::class,
            ContentAccessRepository::class,
        ];
    }

    protected function requiredSeeders(): array
    {
        return [
            SubscriptionTypeNamesSeeder::class,
            SubscriptionExtensionMethodsSeeder::class,
            SubscriptionLengthMethodSeeder::class,
            ContentAccessSeeder::class,
        ];
    }

    private function getSubscriptionType()
    {
        /** @var SubscriptionTypeBuilder $subscriptionTypeBuilder */
        $subscriptionTypeBuilder = $this->inject(SubscriptionTypeBuilder::class);

        $subscriptionTypeRow = $subscriptionTypeBuilder
            ->createNew()
            ->setContentAccessOption('web')
            ->setNameAndUserLabel(random_int(0, 9999))
            ->setActive(1)
            ->setPrice(1)
            ->setLength(30)
            ->save();

        return $subscriptionTypeRow;
    }

    private function getDifferentSubscriptionType()
    {
        /** @var SubscriptionTypeBuilder $subscriptionTypeBuilder */
        $subscriptionTypeBuilder = $this->inject(SubscriptionTypeBuilder::class);

        $subscriptionTypeRow = $subscriptionTypeBuilder
            ->createNew()
            ->setContentAccessOption('web', 'mobile')
            ->setNameAndUserLabel(random_int(0, 9999))
            ->setActive(1)
            ->setPrice(1)
            ->setLength(30)
            ->save();

        return $subscriptionTypeRow;
    }

    private function addSubscription(IRow $subscriptionType, DateTime $from, DateTime $to)
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

    public function testNoSubscription()
    {
        $subscriptionType = $this->getSubscriptionType();
        $nowDate = DateTime::from('2021-02-01');

        $this->extension->setNow($nowDate);
        $this->subscriptionsRepository->setNow($nowDate);
        $result = $this->extension->getStartTime($this->user, $subscriptionType);

        $this->assertEquals($nowDate, $result->getDate());
        $this->assertFalse($result->isExtending());
    }

    public function testActualSubscription()
    {
        $nowDate = DateTime::from('2021-02-01');
        $subscriptionType = $this->getSubscriptionType();
        $this->addSubscription($subscriptionType, $nowDate, $nowDate->modifyClone('+25 days'));

        $this->extension->setNow($nowDate);
        $result = $this->extension->getStartTime($this->user, $subscriptionType);

        $this->assertEquals($nowDate->modifyClone('+25 days'), $result->getDate());
        $this->assertTrue($result->isExtending());
    }

    public function testExpiredSubscription()
    {
        $nowDate = DateTime::from('2021-02-01');
        $subscriptionType = $this->getSubscriptionType();
        $this->addSubscription($subscriptionType, $nowDate->modifyClone('-35 days'), $nowDate->modifyClone('-5 days'));

        $this->extension->setNow($nowDate);
        $result = $this->extension->getStartTime($this->user, $subscriptionType);

        $this->assertEquals($nowDate, $result->getDate());
        $this->assertFalse($result->isExtending());
    }

    public function testMoreSubscriptions()
    {
        $nowDate = DateTime::from('2021-02-01');

        $subscriptionType = $this->getSubscriptionType();
        $this->addSubscription(
            $subscriptionType,
            $nowDate,
            $nowDate->modifyClone('+5 days')
        );

        $differentSubscriptionType = $this->getDifferentSubscriptionType();
        $this->addSubscription(
            $differentSubscriptionType,
            $nowDate->modifyClone('+5 days'),
            $nowDate->modifyClone('+35 days')
        );

        $this->extension->setNow($nowDate);
        $result = $this->extension->getStartTime($this->user, $subscriptionType);
        $this->assertEquals($nowDate->modifyClone('+5 days'), $result->getDate());
        $this->assertTrue($result->isExtending());
    }

    public function testMoreSubscriptionsOverlapping()
    {
        $nowDate = DateTime::from('2021-02-01');

        $subscriptionType = $this->getSubscriptionType();
        $this->addSubscription(
            $subscriptionType,
            $nowDate,
            $nowDate->modifyClone('+10 days')
        );

        $differentSubscriptionType = $this->getDifferentSubscriptionType();
        $this->addSubscription(
            $differentSubscriptionType,
            $nowDate->modifyClone('+5 days'),
            $nowDate->modifyClone('+35 days')
        );

        $this->extension->setNow($nowDate);
        $result = $this->extension->getStartTime($this->user, $subscriptionType);
        $this->assertEquals($nowDate->modifyClone('+10 days'), $result->getDate());
        $this->assertTrue($result->isExtending());
    }

    public function testMoreSubscriptionsNotConnected()
    {
        $nowDate = DateTime::from('2021-02-01');

        $subscriptionType = $this->getSubscriptionType();
        $this->addSubscription(
            $subscriptionType,
            $nowDate,
            $nowDate->modifyClone('+10 days')
        );

        $differentSubscriptionType = $this->getDifferentSubscriptionType();
        $this->addSubscription(
            $differentSubscriptionType,
            $nowDate->modifyClone('+15 days'),
            $nowDate->modifyClone('+35 days')
        );

        $this->extension->setNow($nowDate);
        $result = $this->extension->getStartTime($this->user, $subscriptionType);
        $this->assertEquals($nowDate->modifyClone('+10 days'), $result->getDate());
        $this->assertTrue($result->isExtending());
    }

    public function testMoreSubscriptionAddToLaterSubscription()
    {
        $nowDate = DateTime::from('2021-02-01');

        $differentSubscriptionType = $this->getDifferentSubscriptionType();
        $this->addSubscription(
            $differentSubscriptionType,
            $nowDate,
            $nowDate->modifyClone('+10 days')
        );

        $subscriptionType = $this->getSubscriptionType();
        $this->addSubscription(
            $subscriptionType,
            $nowDate->modifyClone('+15 days'),
            $nowDate->modifyClone('+35 days')
        );

        $this->extension->setNow($nowDate);
        $result = $this->extension->getStartTime($this->user, $subscriptionType);
        $this->assertEquals($nowDate->modifyClone('+35 days'), $result->getDate());
        $this->assertTrue($result->isExtending());
    }
}
