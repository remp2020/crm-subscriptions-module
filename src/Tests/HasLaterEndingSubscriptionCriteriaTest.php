<?php

namespace Crm\SubscriptionsModule\Tests;

use Crm\ApplicationModule\Selection;
use Crm\ApplicationModule\Tests\DatabaseTestCase;
use Crm\SubscriptionsModule\Builder\SubscriptionTypeBuilder;
use Crm\SubscriptionsModule\Repository\ContentAccessRepository;
use Crm\SubscriptionsModule\Repository\SubscriptionExtensionMethodsRepository;
use Crm\SubscriptionsModule\Repository\SubscriptionLengthMethodsRepository;
use Crm\SubscriptionsModule\Repository\SubscriptionTypeNamesRepository;
use Crm\SubscriptionsModule\Repository\SubscriptionsRepository;
use Crm\SubscriptionsModule\Scenarios\HasLaterEndingSubscriptionCriteria;
use Crm\SubscriptionsModule\Seeders\ContentAccessSeeder;
use Crm\SubscriptionsModule\Seeders\SubscriptionExtensionMethodsSeeder;
use Crm\SubscriptionsModule\Seeders\SubscriptionLengthMethodSeeder;
use Crm\SubscriptionsModule\Seeders\SubscriptionTypeNamesSeeder;
use Crm\UsersModule\Auth\UserManager;
use Crm\UsersModule\Repository\UsersRepository;
use Nette\Database\Table\ActiveRow;
use Nette\Utils\DateTime;

class HasLaterEndingSubscriptionCriteriaTest extends DatabaseTestCase
{
    /** @var SubscriptionsRepository */
    private $subscriptionRepository;

    public function setUp(): void
    {
        parent::setUp();

        $this->subscriptionRepository = $this->getRepository(SubscriptionsRepository::class);
    }

    protected function requiredRepositories(): array
    {
        return [
            SubscriptionsRepository::class,
            UsersRepository::class,
            ContentAccessRepository::class,
            SubscriptionExtensionMethodsRepository::class,
            SubscriptionLengthMethodsRepository::class,
            SubscriptionTypeNamesRepository::class,
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

    public function dataProvider(): array
    {
        return [
            'with following subscription' => [
                'first' => ['-28 days', '+2 days'],
                'second' => ['+2 days', '+32 days'],
                'result' => true,
            ],
            'with overlapping subscription' => [
                'first' => ['-20 days', '+10 days'],
                'second' => ['-5 days', '+25 days'],
                'result' => true,
            ],
            'with next subscription, 10 day gap' => [
                'first' => ['-28 days', '+2 days'],
                'second' => ['+12 days', '+42 days'],
                'result' => true,
            ],
            'with following subscription, tested subscription already ended' => [
                'first' => ['-600 seconds', '-599 seconds'],
                'second' => ['-500 seconds', '+30 days'],
                'result' => true,
            ],
            'no next subscription' => [
                'first' => ['-600 seconds', '-599 seconds'],
                'second' => null,
                'result' => false,
            ],
            'with following stopped subscription' => [
                'first' => ['-28 days', '+2 days'],
                'second' => ['+2 days', '+2 days'],
                'result' => false,
            ],
            'with stopped subscription gap' => [
                'first' => ['-28 days', '+2 days'],
                'second' => ['+5 days', '+5 days'],
                'result' => false,
            ],
            'NEGATION: with following subscription' => [
                'first' => ['-28 days', '+2 days'],
                'second' => ['+2 days', '+32 days'],
                'result' => false,
                'negation' => true,
            ],
            'NEGATION: with overlapping subscription' => [
                'first' => ['-20 days', '+10 days'],
                'second' => ['-5 days', '+25 days'],
                'result' => false,
                'negation' => true,
            ],
            'NEGATION: with next subscription, 10 day gap' => [
                'first' => ['-28 days', '+2 days'],
                'second' => ['+12 days', '+42 days'],
                'result' => false,
                'negation' => true,
            ],
            'NEGATION: with following subscription, tested subscription already ended' => [
                'first' => ['-600 seconds', '-599 seconds'],
                'second' => ['-500 seconds', '+30 days'],
                'result' => false,
                'negation' => true,
            ],
            'NEGATION: no next subscription' => [
                'first' => ['-28 days', '+2 days'],
                'second' => null,
                'result' => true,
                'negation' => true,
            ],
            'NEGATION: with following stopped subscription' => [
                'first' => ['-28 days', '+2 days'],
                'second' => ['+2 days', '+2 days'],
                'result' => true,
                'negation' => true,
            ],
            'NEGATION: with stopped subscription gap' => [
                'first' => ['-28 days', '+2 days'],
                'second' => ['+5 days', '+5 days'],
                'result' => true,
                'negation' => true,
            ],
        ];
    }

    /**
     * @dataProvider dataProvider
     */
    public function testScenarios(array $first, ?array $second, bool $result, bool $negation = false): void
    {
        /**
         * @var Selection $subscriptionSelection
         * @var ActiveRow $subscriptionRow
         */
        [$subscriptionSelection, $subscriptionRow] = $this->getSubscriptionWithConditionSelection($first[0], $first[1]);
        if ($second) {
            [$_, $_] = $this->getSubscriptionWithConditionSelection($second[0], $second[1]);
        }

        $criteria = $this->inject(HasLaterEndingSubscriptionCriteria::class);
        $values = (object)['selection' => !$negation];

        $this->assertTrue(
            $criteria->addConditions($subscriptionSelection, [HasLaterEndingSubscriptionCriteria::KEY => $values], $subscriptionRow)
        );
        $this->assertEquals((int) $result, $subscriptionSelection->count());
    }

    private $subscriptionTypeRow;
    private $userRow;
    private function getSubscriptionWithConditionSelection(string $startTime, string $endTime): array
    {
        if (!$this->subscriptionTypeRow) {
            /** @var SubscriptionTypeBuilder $subscriptionTypeBuilder */
            $subscriptionTypeBuilder = $this->inject(SubscriptionTypeBuilder::class);

            $this->subscriptionTypeRow = $subscriptionTypeBuilder
                ->createNew()
                ->setNameAndUserLabel('Test')
                ->setActive(1)
                ->setPrice(1)
                ->setLength(30)
                ->save();
        }

        if (!$this->userRow) {
            /** @var UserManager $userManager */
            $userManager = $this->inject(UserManager::class);
            $this->userRow = $userManager->addNewUser("test@example.com");
        }

        $subscriptionRow = $this->subscriptionRepository->add(
            $this->subscriptionTypeRow,
            false,
            false,
            $this->userRow,
            SubscriptionsRepository::TYPE_REGULAR,
            DateTime::from($startTime),
            DateTime::from($endTime)
        );

        $subscriptionSelection = $this->subscriptionRepository->getTable()
            ->where(['subscriptions.id' => $subscriptionRow]);

        return [$subscriptionSelection, $subscriptionRow];
    }
}
