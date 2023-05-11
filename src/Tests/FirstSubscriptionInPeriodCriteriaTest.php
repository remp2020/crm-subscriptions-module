<?php

namespace Crm\SubscriptionsModule\Tests;

use Crm\ApplicationModule\Tests\DatabaseTestCase;
use Crm\SubscriptionsModule\Builder\SubscriptionTypeBuilder;
use Crm\SubscriptionsModule\Repository\SubscriptionTypesRepository;
use Crm\SubscriptionsModule\Repository\SubscriptionsRepository;
use Crm\SubscriptionsModule\Scenarios\FirstSubscriptionInPeriodCriteria;
use Crm\SubscriptionsModule\Seeders\SubscriptionExtensionMethodsSeeder;
use Crm\SubscriptionsModule\Seeders\SubscriptionLengthMethodSeeder;
use Crm\SubscriptionsModule\Seeders\SubscriptionTypeNamesSeeder;
use Crm\UsersModule\Auth\UserManager;
use Crm\UsersModule\Repository\UsersRepository;
use Nette\Utils\DateTime;

class FirstSubscriptionInPeriodCriteriaTest extends DatabaseTestCase
{
    protected function requiredRepositories(): array
    {
        return [
            SubscriptionTypesRepository::class,
            SubscriptionsRepository::class,
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

    /**
     * @dataProvider dataProviderForTestDonationAmountCriteria
     */
    public function testFirstSubscriptionInPeriodCriteria(
        string $currentStartTime,
        ?string $previousStartTime,
        mixed $intervalDays,
        bool $expectedValue,
        ?\Exception $expectedException = null
    ) {
        [$subscriptionSelection, $subscriptionRow] = $this->prepareData($previousStartTime, $currentStartTime);

        $criteria = $this->inject(FirstSubscriptionInPeriodCriteria::class);
        $values = (object)['selection' => $intervalDays];

        if ($expectedException !== null) {
            $this->expectExceptionObject($expectedException);
        }

        $criteria->addConditions($subscriptionSelection, [FirstSubscriptionInPeriodCriteria::KEY => $values], $subscriptionRow);

        if ($expectedValue) {
            $this->assertNotNull($subscriptionSelection->fetch());
        } else {
            $this->assertNull($subscriptionSelection->fetch());
        }
    }


    public function dataProviderForTestDonationAmountCriteria(): array
    {
        return [
            'SingleSubscription_ShouldBeTrue' => [
                'currentStartTime' => 'now',
                'previousStartTime' => null,
                'intervalDays' => 50,
                'expectedValue' => true,
            ],
            'PreviousSubscriptionWithinPeriod_ShouldBeFalse' => [
                'currentStartTime' => 'now',
                'previousStartTime' => '-20 days',
                'intervalDays' => 50,
                'expectedValue' => false,
            ],
            'PreviousSubscriptionOutsidePeriod_ShouldBeTrue' => [
                'currentStartTime' => 'now',
                'previousStartTime' => '-100 days',
                'intervalDays' => 50,
                'expectedValue' => true,
            ],
            'Fail_ValueIsNegative' => [
                'currentStartTime' => 'now',
                'previousStartTime' => null,
                'intervalDays' => -10,
                'expectedValue' => false,
                'expectedException' => new \Exception("Provided value [-10] for number of days is not valid positive integer."),
            ],
            'Fail_ValueIsString' => [
                'currentStartTime' => 'now',
                'previousStartTime' => null,
                'intervalDays' => 'random_string',
                'expectedValue' => false,
                'expectedException' => new \Exception("Provided value [random_string] for number of days is not valid positive integer."),
            ],
        ];
    }

    private function prepareData(?string $previousStartTime, string $currentStartTime)
    {
        /** @var UserManager $userManager */
        $userManager = $this->inject(UserManager::class);
        $userRow = $userManager->addNewUser('test@test.sk');

        /** @var SubscriptionTypeBuilder $subscriptionTypeBuilder */
        $subscriptionTypeBuilder = $this->inject(SubscriptionTypeBuilder::class);
        $subscriptionTypeRow = $subscriptionTypeBuilder->createNew()
            ->setNameAndUserLabel('test')
            ->setLength(10)
            ->setPrice(1)
            ->setActive(1)
            ->save();

        /** @var SubscriptionsRepository $subscriptionsRepository */
        $subscriptionsRepository = $this->getRepository(SubscriptionsRepository::class);

        if ($previousStartTime) {
            $subscriptionsRepository->add(
                subscriptionType: $subscriptionTypeRow,
                isRecurrent: false,
                isPaid: false,
                user: $userRow,
                startTime: DateTime::from($previousStartTime),
            );
        }
        $subscriptionRow = $subscriptionsRepository->add(
            subscriptionType: $subscriptionTypeRow,
            isRecurrent: false,
            isPaid: false,
            user: $userRow,
            startTime: DateTime::from($currentStartTime),
        );

        $subscriptionSelection = $subscriptionsRepository->getTable()
            ->where(['subscriptions.id' => $subscriptionRow->id]);

        return [$subscriptionSelection, $subscriptionRow];
    }
}
