<?php

namespace Crm\SubscriptionsModule\Tests;

use Crm\ApplicationModule\Tests\DatabaseTestCase;
use Crm\SubscriptionsModule\Models\Builder\SubscriptionTypeBuilder;
use Crm\SubscriptionsModule\Models\Extension\ExtendSameContentAccess;
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
use Crm\UsersModule\Auth\UserManager;
use Crm\UsersModule\Repository\UsersRepository;
use Nette\Database\Table\ActiveRow;
use Nette\Utils\DateTime;

class ExtendSameContentAccessExtensionTest extends DatabaseTestCase
{
    private ExtendSameContentAccess $extension;

    private SubscriptionsRepository $subscriptionsRepository;

    private ActiveRow $user;

    public function setUp(): void
    {
        parent::setUp();

        $this->extension = $this->inject(ExtendSameContentAccess::class);
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
            // use only seeded accesses Crm\SubscriptionsModule\Seeders\ContentAccessSeeder
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
            // use only seeded accesses Crm\SubscriptionsModule\Seeders\ContentAccessSeeder
            ->setContentAccessOption('web', 'print')
            ->setNameAndUserLabel(random_int(0, 9999))
            ->setActive(1)
            ->setPrice(1)
            ->setLength(30)
            ->save();

        return $subscriptionTypeRow;
    }

    private function addSubscription(ActiveRow $subscriptionType, DateTime $from, DateTime $to)
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

    public function testDifferentActualSubscription()
    {
        $nowDate = DateTime::from('2021-02-01');

        $subscriptionType = $this->getSubscriptionType();

        $differentSubscriptionType = $this->getDifferentSubscriptionType();
        $this->addSubscription(
            $differentSubscriptionType,
            $nowDate,
            $nowDate->modifyClone('+25 days')
        );

        $this->extension->setNow($nowDate);
        $result = $this->extension->getStartTime($this->user, $subscriptionType);

        // active subscription has different content access; start immediatelly
        $this->assertEquals($nowDate, $result->getDate());
        $this->assertFalse($result->isExtending());
    }

    public function testMoreSubscriptionsConnected()
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

    public function testMoreSubscriptionsDiffOrderConnected()
    {
        $nowDate = DateTime::from('2021-02-01');

        $differentSubscriptionType = $this->getDifferentSubscriptionType();
        $this->addSubscription(
            $differentSubscriptionType,
            $nowDate,
            $nowDate->modifyClone('+5 days')
        );

        $subscriptionType = $this->getSubscriptionType();
        $this->addSubscription(
            $subscriptionType,
            $nowDate->modifyClone('+5 days'),
            $nowDate->modifyClone('+35 days')
        );

        $this->extension->setNow($nowDate);
        $result = $this->extension->getStartTime($this->user, $subscriptionType);
        $this->assertEquals($nowDate->modifyClone('+35 days'), $result->getDate());
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

    public function testMoreSubscriptionDiffOrderNotConnected()
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
