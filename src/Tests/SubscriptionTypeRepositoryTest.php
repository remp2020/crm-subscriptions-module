<?php

namespace Crm\SubscriptionsModule\Tests;

use Crm\ApplicationModule\Tests\DatabaseTestCase;
use Crm\SubscriptionsModule\Events\ContentAccessAssignedEvent;
use Crm\SubscriptionsModule\Events\ContentAccessUnassignedEvent;
use Crm\SubscriptionsModule\Events\SubscriptionTypeCreatedEvent;
use Crm\SubscriptionsModule\Events\SubscriptionTypeUpdatedEvent;
use Crm\SubscriptionsModule\Models\Builder\SubscriptionTypeBuilder;
use Crm\SubscriptionsModule\Repositories\ContentAccessRepository;
use Crm\SubscriptionsModule\Repositories\SubscriptionExtensionMethodsRepository;
use Crm\SubscriptionsModule\Repositories\SubscriptionLengthMethodsRepository;
use Crm\SubscriptionsModule\Repositories\SubscriptionTypeContentAccessRepository;
use Crm\SubscriptionsModule\Repositories\SubscriptionTypeItemsRepository;
use Crm\SubscriptionsModule\Repositories\SubscriptionTypesRepository;
use Crm\SubscriptionsModule\Seeders\ContentAccessSeeder;
use Crm\SubscriptionsModule\Seeders\SubscriptionExtensionMethodsSeeder;
use Crm\SubscriptionsModule\Seeders\SubscriptionLengthMethodSeeder;
use League\Event\AbstractListener;
use League\Event\Emitter;

class SubscriptionTypeRepositoryTest extends DatabaseTestCase
{
    private SubscriptionTypesRepository $subscriptionTypesRepository;
    private ContentAccessRepository $contentAccessRepository;
    private Emitter $emitter;
    private SubscriptionTypeBuilder $subscriptionTypeBuilder;

    protected function requiredRepositories(): array
    {
        return [
            SubscriptionExtensionMethodsRepository::class,
            SubscriptionLengthMethodsRepository::class,
            SubscriptionTypesRepository::class,
            SubscriptionTypeItemsRepository::class,
            SubscriptionTypeContentAccessRepository::class,
            ContentAccessRepository::class,
        ];
    }

    protected function requiredSeeders(): array
    {
        return [
            SubscriptionExtensionMethodsSeeder::class,
            SubscriptionLengthMethodSeeder::class,
            ContentAccessSeeder::class
        ];
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->subscriptionTypesRepository = $this->getRepository(SubscriptionTypesRepository::class);
        $this->contentAccessRepository = $this->getRepository(ContentAccessRepository::class);
        $this->emitter = $this->inject(Emitter::class);
        $this->subscriptionTypeBuilder = $this->inject(SubscriptionTypeBuilder::class);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        \Mockery::close();
    }

    public function testSubscriptionTypeCreateEvents()
    {
        $this->emitter->addListener(SubscriptionTypeCreatedEvent::class, $this->mockListener(times: 1));
        $this->emitter->addListener(SubscriptionTypeUpdatedEvent::class, $this->mockListener(times: 0));
        $this->emitter->addListener(ContentAccessAssignedEvent::class, $this->mockListener(times: 2));
        $this->emitter->addListener(ContentAccessUnassignedEvent::class, $this->mockListener(times: 0));

        // should emit "created" 1x and "content access assigned" 2x
        $this->createSubscriptionType('web', 'print');
    }

    public function testSubscriptionTypeUpdateEvents()
    {
        $this->emitter->addListener(SubscriptionTypeCreatedEvent::class, $this->mockListener(times: 1));
        $this->emitter->addListener(SubscriptionTypeUpdatedEvent::class, $this->mockListener(times: 1));
        $this->emitter->addListener(ContentAccessAssignedEvent::class, $this->mockListener(times: 2));
        $this->emitter->addListener(ContentAccessUnassignedEvent::class, $this->mockListener(times: 0));

        // should emit "created" 1x and "content access assigned" 2x
        $subscriptionType = $this->createSubscriptionType('web', 'print');

        // should emit "updated" 1x
        $this->subscriptionTypesRepository->update($subscriptionType, [
            'ask_address' => true,
        ]);
    }

    public function testSubscriptionTypeContentAccessEvents()
    {
        $this->emitter->addListener(SubscriptionTypeCreatedEvent::class, $this->mockListener(times: 1));
        $this->emitter->addListener(SubscriptionTypeUpdatedEvent::class, $this->mockListener(times: 0));
        $this->emitter->addListener(ContentAccessAssignedEvent::class, $this->mockListener(times: 3));
        $this->emitter->addListener(ContentAccessUnassignedEvent::class, $this->mockListener(times: 2));

        // should emit "created" 1x and "content access assigned" 2x
        $subscriptionType = $this->createSubscriptionType('web', 'print');

        // should "content access assigned" 1x
        $this->contentAccessRepository->addAccess($subscriptionType, 'mobile');
        // should "content access unassigned" 1x
        $this->contentAccessRepository->removeAccess($subscriptionType, 'web');
        // should "content access unassigned" 1x
        $this->contentAccessRepository->removeAccess($subscriptionType, 'print');
    }


    private function mockListener(int $times)
    {
        $this->addToAssertionCount(1);
        return \Mockery::mock(AbstractListener::class)->shouldReceive('handle')->times($times)->getMock();
    }

    private function createSubscriptionType(string ...$contentAccess)
    {
        $builder = $this->subscriptionTypeBuilder
            ->createNew()
            ->setName('test_subscription')
            ->setUserLabel('')
            ->setActive(true)
            ->setPrice(1)
            ->setLength(365);

        if (count($contentAccess) > 0) {
            $builder->setContentAccessOption(...$contentAccess);
        }

        return $builder->save();
    }
}
