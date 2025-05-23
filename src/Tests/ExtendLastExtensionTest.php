<?php

namespace Crm\SubscriptionsModule\Tests;

use Crm\ApplicationModule\Tests\DatabaseTestCase;
use Crm\SubscriptionsModule\Models\Builder\SubscriptionTypeBuilder;
use Crm\SubscriptionsModule\Models\Extension\ExtendLastExtension;
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
use Random\RandomException;

class ExtendLastExtensionTest extends DatabaseTestCase
{
    private SubscriptionsRepository $subscriptionsRepository;

    private ExtendLastExtension $extension;

    private ActiveRow $user;

    public function setUp(): void
    {
        parent::setUp();

        $this->extension = $this->inject(ExtendLastExtension::class);
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
            ContentAccessSeeder::class,
        ];
    }

    private function getWebSubscriptionType()
    {
        return $this->getSubscriptionTypeWithContentAccess('web');
    }

    private function getWebAndPrintSubscriptionType()
    {
        return $this->getSubscriptionTypeWithContentAccess('web', 'print');
    }

    /**
     * @param ...$contentAccess string only seeded accesses Crm\SubscriptionsModule\Seeders\ContentAccessSeeder
     *
     * @return bool|int|ActiveRow
     * @throws RandomException
     */
    private function getSubscriptionTypeWithContentAccess(string ...$contentAccess)
    {
        /** @var SubscriptionTypeBuilder $subscriptionTypeBuilder */
        $subscriptionTypeBuilder = $this->inject(SubscriptionTypeBuilder::class);

        $subscriptionTypeRow = $subscriptionTypeBuilder
            ->createNew()
            ->setContentAccessOption(...$contentAccess)
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

        $subscriptionType = $this->getWebSubscriptionType();

        $result = $this->extension->getStartTime($this->user, $subscriptionType);

        $this->assertEquals($nowDate, $result->getDate());
        $this->assertFalse($result->isExtending());
    }

    // same as ExtendActualExtensionTest::testActualSubscription
    public function testActualSubscription()
    {
        $nowDate = DateTime::from('2021-02-01');
        $this->extension->setNow($nowDate);
        $this->subscriptionsRepository->setNow($nowDate);

        $subscriptionType = $this->getWebSubscriptionType();
        $this->addSubscription($subscriptionType, $nowDate->modifyClone('-5 days'), $nowDate->modifyClone('+25 days'));

        $result = $this->extension->getStartTime($this->user, $subscriptionType);

        $this->assertEquals($nowDate->modifyClone('+25 days'), $result->getDate());
        $this->assertTrue($result->isExtending());
    }

    // same as ExtendActualExtensionTest::testExpiredSubscription
    public function testExpiredSubscription()
    {
        $nowDate = DateTime::from('2021-02-01');
        $this->extension->setNow($nowDate);
        $this->subscriptionsRepository->setNow($nowDate);

        $subscriptionType = $this->getWebSubscriptionType();
        $this->addSubscription($subscriptionType, $nowDate->modifyClone('-35 days'), $nowDate->modifyClone('-5 days'));

        $result = $this->extension->getStartTime($this->user, $subscriptionType);

        $this->assertEquals($nowDate, $result->getDate());
        $this->assertFalse($result->isExtending());
    }

    // different than ExtendActualExtensionTest::testLastActualSubscription
    public function testLastActualSubscription()
    {
        $nowDate = DateTime::from('2021-02-01');
        $this->extension->setNow($nowDate);
        $this->subscriptionsRepository->setNow($nowDate);

        $subscriptionType = $this->getWebSubscriptionType();
        $subscriptionTypeDifferent = $this->getWebAndPrintSubscriptionType();
        $this->addSubscription($subscriptionType, $nowDate->modifyClone('-5 days'), $nowDate->modifyClone('+25 days'));
        $this->addSubscription($subscriptionTypeDifferent, $nowDate->modifyClone('-10 days'), $nowDate->modifyClone('+20 days'));
        // this subscription will be extended; we ignore gaps and search for last one
        $this->addSubscription($subscriptionType, $nowDate->modifyClone('+180 days'), $nowDate->modifyClone('+210 days'));

        $result = $this->extension->getStartTime($this->user, $subscriptionType);

        $this->assertEquals($nowDate->modifyClone('+210 days'), $result->getDate());
        $this->assertTrue($result->isExtending());
    }

    public function testFutureSubscription()
    {
        $nowDate = DateTime::from('2021-02-01');
        $this->extension->setNow($nowDate);
        $this->subscriptionsRepository->setNow($nowDate);

        $subscriptionType = $this->getWebSubscriptionType();
        $this->addSubscription($subscriptionType, $nowDate->modifyClone('+180 days'), $nowDate->modifyClone('+210 days'));

        $result = $this->extension->getStartTime($this->user, $subscriptionType);

        $this->assertEquals($nowDate->modifyClone('+210 days'), $result->getDate());
        $this->assertTrue($result->isExtending());
    }

    public function testSubscriptionDifferentContentTypes()
    {
        $nowDate = DateTime::from('2021-02-01');
        $this->extension->setNow($nowDate);
        $this->subscriptionsRepository->setNow($nowDate);

        $subscriptionType = $this->getWebSubscriptionType();
        $this->addSubscription($subscriptionType, $nowDate->modifyClone('+180 days'), $nowDate->modifyClone('+210 days'));

        $subscriptionTypeDifferent = $this->getWebAndPrintSubscriptionType();
        $result = $this->extension->getStartTime($this->user, $subscriptionTypeDifferent);

        $this->assertEquals($nowDate->modifyClone('+210 days'), $result->getDate());
        $this->assertTrue($result->isExtending());
    }

    public function testSubscriptionDifferentIgnoredContentTypes()
    {
        $nowDate = DateTime::from('2021-02-01');
        $this->extension->setNow($nowDate);
        $this->subscriptionsRepository->setNow($nowDate);

        $subscriptionType = $this->getSubscriptionTypeWithContentAccess('mobile');
        $this->addSubscription($subscriptionType, $nowDate->modifyClone('+180 days'), $nowDate->modifyClone('+210 days'));

        $subscriptionType2 = $this->getWebSubscriptionType();
        $this->extension->setIgnoreSubscriptionsWithContentAccess('mobile');
        $result = $this->extension->getStartTime($this->user, $subscriptionType2);

        // should not extend
        $this->assertEquals($nowDate, $result->getDate());
        $this->assertFalse($result->isExtending());
    }
}
