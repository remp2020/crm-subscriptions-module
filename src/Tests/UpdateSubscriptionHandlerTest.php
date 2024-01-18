<?php

namespace Crm\SubscriptionsModule\Tests;

use Crm\ApplicationModule\Tests\DatabaseTestCase;
use Crm\SubscriptionsModule\Api\v1\UpdateSubscriptionHandler;
use Crm\SubscriptionsModule\Models\Builder\SubscriptionTypeBuilder;
use Crm\SubscriptionsModule\Repositories\SubscriptionTypesRepository;
use Crm\SubscriptionsModule\Repositories\SubscriptionsRepository;
use Crm\SubscriptionsModule\Seeders\SubscriptionExtensionMethodsSeeder;
use Crm\SubscriptionsModule\Seeders\SubscriptionLengthMethodSeeder;
use Crm\SubscriptionsModule\Seeders\SubscriptionTypeNamesSeeder;
use Crm\UsersModule\Auth\UserManager;
use Crm\UsersModule\Repository\UsersRepository;
use Nette\Http\IResponse;
use Nette\Utils\DateTime;
use Tomaj\NetteApi\Response\JsonApiResponse;

class UpdateSubscriptionHandlerTest extends DatabaseTestCase
{
    private UpdateSubscriptionHandler $updateSubscriptionHandler;

    private SubscriptionsRepository $subscriptionsRepository;

    public function setUp(): void
    {
        parent::setUp();

        $this->updateSubscriptionHandler = $this->inject(UpdateSubscriptionHandler::class);
        $this->subscriptionsRepository = $this->getRepository(SubscriptionsRepository::class);
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

    public function testUpdateWithWrongReferences(): void
    {
        [$subscriptionType, $subscription] = $this->prepareDataForTesting();

        // missing subscription
        /** @var JsonApiResponse $response */
        $response = $this->updateSubscriptionHandler->handle([
            'id' => $subscription->id + 1,
            'subscription_type_id' => $subscriptionType->id,
        ]);
        $this->assertEquals(IResponse::S404_NotFound, $response->getCode());
        $payload = $response->getPayload();
        $this->assertEquals('subscription_not_found', $payload['code']);

        // missing subscription type
        /** @var JsonApiResponse $response */
        $response = $this->updateSubscriptionHandler->handle([
            'id' => $subscription->id,
            'subscription_type_id' => $subscriptionType->id + 1,
        ]);
        $this->assertEquals(IResponse::S404_NotFound, $response->getCode());
        $payload = $response->getPayload();
        $this->assertEquals('subscription_type_not_found', $payload['code']);

        // wrong type
        /** @var JsonApiResponse $response */
        $response = $this->updateSubscriptionHandler->handle([
            'id' => $subscription->id,
            'type' => 'wrong_type',
        ]);
        $this->assertEquals(IResponse::S400_BadRequest, $response->getCode());
        $payload = $response->getPayload();
        $this->assertEquals('wrong_type', $payload['code']);


        // wrong date format
        $now = new DateTime();
        /** @var JsonApiResponse $response */
        $response = $this->updateSubscriptionHandler->handle([
            'id' => $subscription->id,
            'end_time' => $now->format('Y-m-d H:i:s'),
        ]);
        $this->assertEquals(IResponse::S400_BadRequest, $response->getCode());
        $payload = $response->getPayload();
        $this->assertEquals('end_time_wrong_format', $payload['code']);
    }

    public function testValidUpdateCall(): void
    {
        [, $subscription] = $this->prepareDataForTesting();

        /** @var DateTime $subscriptionEndTime */
        $subscriptionEndTime = $subscription->end_time;
        $newSubscriptionEndTime = $subscriptionEndTime->modify('-1 day');

        /** @var JsonApiResponse $response */
        $response = $this->updateSubscriptionHandler->handle([
            'id' => $subscription->id,
            'end_time' => $newSubscriptionEndTime->format(DateTime::RFC3339)
        ]);

        $this->assertEquals(IResponse::S200_OK, $response->getCode());

        $data = $response->getPayload();

        $subscription = $this->subscriptionsRepository->find($subscription->id);

        $this->assertEquals($data['subscription']['end_time'], $newSubscriptionEndTime->format(DATE_RFC3339));
        $this->assertEquals($newSubscriptionEndTime, $subscription->end_time);
    }

    private function prepareDataForTesting(): array
    {
        /** @var SubscriptionTypeBuilder $subscriptionTypeBuilder */
        $subscriptionTypeBuilder = $this->inject(SubscriptionTypeBuilder::class);

        $subscriptionType = $subscriptionTypeBuilder
            ->createNew()
            ->setNameAndUserLabel(random_int(0, 9999))
            ->setActive(1)
            ->setPrice(1)
            ->setLength(30)
            ->save();

        /** @var UserManager $userManager */
        $userManager = $this->inject(UserManager::class);
        $user = $userManager->addNewUser('test@example.com');

        $subscription = $this->subscriptionsRepository->add(
            $subscriptionType,
            false,
            true,
            $user,
            SubscriptionsRepository::TYPE_REGULAR
        );

        return [$subscriptionType, $subscription];
    }
}
