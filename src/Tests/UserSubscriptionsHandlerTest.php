<?php

namespace Crm\SubscriptionsModule\Tests;

use Crm\ApiModule\Tests\ApiTestTrait;
use Crm\ApplicationModule\Tests\DatabaseTestCase;
use Crm\SubscriptionsModule\Api\v1\UsersSubscriptionsHandler;
use Crm\SubscriptionsModule\Builder\SubscriptionTypeBuilder;
use Crm\SubscriptionsModule\Repository\SubscriptionTypesRepository;
use Crm\SubscriptionsModule\Repository\SubscriptionsRepository;
use Crm\SubscriptionsModule\Seeders\ContentAccessSeeder;
use Crm\SubscriptionsModule\Seeders\SubscriptionExtensionMethodsSeeder;
use Crm\SubscriptionsModule\Seeders\SubscriptionLengthMethodSeeder;
use Crm\SubscriptionsModule\Seeders\SubscriptionTypeNamesSeeder;
use Crm\UsersModule\Auth\UserManager;
use Crm\UsersModule\Repository\AccessTokensRepository;
use Crm\UsersModule\Repository\AddressTypesRepository;
use Crm\UsersModule\Repository\AddressesRepository;
use Crm\UsersModule\Repository\UsersRepository;
use Crm\UsersModule\Tests\TestUserTokenAuthorization;
use Nette\Database\Table\ActiveRow;
use Nette\Http\Response;

class UserSubscriptionsHandlerTest extends DatabaseTestCase
{
    use ApiTestTrait;

    private AddressesRepository $addressesRepository;
    private UserManager $userManager;
    private AddressTypesRepository $addressTypesRepository;
    private UsersSubscriptionsHandler $apiHandler;

    /** @var SubscriptionsRepository */
    private $subscriptionsRepository;

    public function setUp(): void
    {
        parent::setUp();

        $this->subscriptionsRepository = $this->getRepository(SubscriptionsRepository::class);
        $this->apiHandler = $this->inject(UsersSubscriptionsHandler::class);
    }

    protected function requiredRepositories(): array
    {
        return [
            SubscriptionsRepository::class,
            UsersRepository::class,
            AccessTokensRepository::class,
            SubscriptionTypesRepository::class
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

    public function testValidCall(): void
    {
        $userEmail = 'user@example.com';
        $user = $this->prepareUserData($userEmail);
        $subscriptionType = $this->prepareSubscriptionTypeData('Test', 'test-' . time());
        $subscription = $this->prepareSubscriptionData($user, $subscriptionType);

        $token = $this->getRepository(AccessTokensRepository::class)
            ->allUserTokens($user->id)
            ->limit(1)
            ->fetch();

        $authorization = new TestUserTokenAuthorization($token, $user);
        $this->apiHandler->setAuthorization($authorization);

        $response = $this->runJsonApi($this->apiHandler);
        $this->assertEquals(Response::S200_OK, $response->getCode());

        $payload = $response->getPayload();
        $this->assertEquals('ok', $payload['status']);
        $this->assertCount(1, $payload['subscriptions']);

        $responseSubscription = $payload['subscriptions'][0];

        $this->assertEquals($responseSubscription['id'], $subscription->id);
        $this->assertEquals($responseSubscription['code'], $subscription->subscription_type->code);
        $this->assertEquals($responseSubscription['name'], $subscription->subscription_type->name);
        $this->assertEquals($responseSubscription['label'], $subscription->subscription_type->user_label);
    }

    public function testValidCallMultipleSubscriptions(): void
    {
        $userEmail = 'user@example.com';
        $user = $this->prepareUserData($userEmail);
        $subscriptionType = $this->prepareSubscriptionTypeData('Test', 'test-' . time());
        // Order sorted by end_dates
        $subscription1 = $this->prepareSubscriptionData($user, $subscriptionType, 5, 20);
        $subscription2 = $this->prepareSubscriptionData($user, $subscriptionType, 15, 5);

        $token = $this->getRepository(AccessTokensRepository::class)
            ->allUserTokens($user->id)
            ->limit(1)
            ->fetch();

        $authorization = new TestUserTokenAuthorization($token, $user);
        $this->apiHandler->setAuthorization($authorization);

        $response = $this->runJsonApi($this->apiHandler);
        $this->assertEquals(Response::S200_OK, $response->getCode());

        $payload = $response->getPayload();
        $this->assertEquals('ok', $payload['status']);
        $this->assertCount(2, $payload['subscriptions']);

        $responseSubscription1 = $payload['subscriptions'][0];
        $responseSubscription2 = $payload['subscriptions'][1];

        $this->assertEquals($responseSubscription1['id'], $subscription1->id);
        $this->assertEquals($responseSubscription1['code'], $subscriptionType->code);
        $this->assertEquals($responseSubscription1['name'], $subscriptionType->name);
        $this->assertEquals($responseSubscription1['label'], $subscriptionType->user_label);

        $this->assertEquals($responseSubscription2['id'], $subscription2->id);
        $this->assertEquals($responseSubscription2['code'], $subscriptionType->code);
        $this->assertEquals($responseSubscription2['name'], $subscriptionType->name);
        $this->assertEquals($responseSubscription2['label'], $subscriptionType->user_label);
    }

    private function prepareUserData($userEmail): ActiveRow
    {
        /** @var UserManager $userManager */
        $userManager = $this->inject(UserManager::class);

        return $userManager->addNewUser($userEmail);
    }

    private function prepareSubscriptionTypeData($nameAndLabel, $code): ActiveRow
    {
        /** @var SubscriptionTypeBuilder $subscriptionTypeBuilder */
        $subscriptionTypeBuilder = $this->inject(SubscriptionTypeBuilder::class);

        return $subscriptionTypeBuilder
            ->createNew()
            ->setNameAndUserLabel($nameAndLabel)
            ->setCode($code)
            ->setActive(1)
            ->setPrice(1)
            ->setLength(31)
            ->setContentAccessOption('web')
            ->save();
    }

    private function prepareSubscriptionData(
        $user,
        $subscriptionType,
        int $startInterval = 15,
        int $endInterval = 15
    ): ActiveRow {
        $subscriptionRow = $this->subscriptionsRepository->add(
            $subscriptionType,
            false,
            true,
            $user,
            SubscriptionsRepository::TYPE_REGULAR,
            (new \DateTime())->sub(new \DateInterval("P{$startInterval}D")),
            (new \DateTime())->add(new \DateInterval("P{$endInterval}D")),
        );

        return $subscriptionRow;
    }
}
