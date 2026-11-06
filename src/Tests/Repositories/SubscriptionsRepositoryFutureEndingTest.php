<?php
declare(strict_types=1);

namespace Crm\SubscriptionsModule\Tests\Repositories;

use Crm\ApplicationModule\Models\NowTrait;
use Crm\ApplicationModule\Tests\DatabaseTestCase;
use Crm\SubscriptionsModule\Models\Builder\SubscriptionTypeBuilder;
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

class SubscriptionsRepositoryFutureEndingTest extends DatabaseTestCase
{
    use NowTrait;

    private SubscriptionsRepository $subscriptionsRepository;
    private UserManager $userManager;
    private ActiveRow $user;

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

        $this->setNow(DateTime::from('2022-01-15 00:00:00'));

        $this->subscriptionsRepository = $this->getRepository(SubscriptionsRepository::class);
        $this->subscriptionsRepository->setNow($this->getNow());
        $this->userManager = $this->inject(UserManager::class);
        $this->user = $this->userManager->addNewUser('test@example.com');
    }

    protected function tearDown(): void
    {
        $this->subscriptionsRepository->setNow(null);

        parent::tearDown();
    }

    public function testFutureEndingUserSubscriptions(): void
    {
        $webType = $this->addSubscriptionType('web');
        $otherUser = $this->userManager->addNewUser('other@example.com');

        $this->addSubscription($webType, $this->user, '-60 days', '-30 days');
        $active = $this->addSubscription($webType, $this->user, '-10 days', '+20 days');
        $future = $this->addSubscription($webType, $this->user, '+20 days', '+50 days');
        $this->addSubscription($webType, $this->user, '+90 days', '+90 days');
        $this->addSubscription($webType, $otherUser, '-10 days', '+20 days');

        $subscriptionIds = $this->subscriptionsRepository->futureEndingUserSubscriptions($this->user->id)
            ->fetchPairs('id', 'id');

        $this->assertSame([$future->id => $future->id, $active->id => $active->id], $subscriptionIds);
    }

    public function testFutureEndingUserSubscriptionsWithContentAccess(): void
    {
        $webType = $this->addSubscriptionType('web');
        $printType = $this->addSubscriptionType('print');
        $webPrintType = $this->addSubscriptionType('web', 'print');

        $web = $this->addSubscription($webType, $this->user, '-10 days', '+20 days');
        $webPrint = $this->addSubscription($webPrintType, $this->user, '-5 days', '+30 days');
        $print = $this->addSubscription($printType, $this->user, '-10 days', '+40 days');
        $this->addSubscription($printType, $this->user, '-60 days', '-30 days');
        $this->addSubscription($printType, $this->user, '+90 days', '+90 days');

        $printSubscriptionIds = $this->subscriptionsRepository
            ->futureEndingUserSubscriptionsWithContentAccess($this->user->id, ['print'])
            ->fetchPairs('id', 'id');
        $this->assertSame([$print->id => $print->id, $webPrint->id => $webPrint->id], $printSubscriptionIds);

        $webOrPrintSubscriptionIds = $this->subscriptionsRepository
            ->futureEndingUserSubscriptionsWithContentAccess($this->user->id, ['web', 'print'])
            ->fetchPairs('id', 'id');
        $this->assertSame(
            [$print->id => $print->id, $webPrint->id => $webPrint->id, $web->id => $web->id],
            $webOrPrintSubscriptionIds,
        );
    }

    private function addSubscriptionType(string ...$contentAccessNames): ActiveRow
    {
        return $this->inject(SubscriptionTypeBuilder::class)
            ->createNew()
            ->setNameAndUserLabel(implode('_', $contentAccessNames))
            ->setContentAccessOption(...$contentAccessNames)
            ->setActive(true)
            ->setPrice(1)
            ->setLength(30)
            ->save();
    }

    private function addSubscription(
        ActiveRow $subscriptionType,
        ActiveRow $user,
        string $startModifier,
        string $endModifier,
    ): ActiveRow {
        return $this->subscriptionsRepository->add(
            subscriptionType: $subscriptionType,
            isRecurrent: false,
            isPaid: true,
            user: $user,
            startTime: $this->getNow()->modify($startModifier),
            endTime: $this->getNow()->modify($endModifier),
            sendEmail: false,
        );
    }
}
