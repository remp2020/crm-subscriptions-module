<?php

namespace Crm\SubscriptionsModule\Tests;

use Crm\ApplicationModule\Models\NowTrait;
use Crm\ApplicationModule\Tests\DatabaseTestCase;
use Crm\SubscriptionsModule\Models\Builder\SubscriptionTypeBuilder;
use Crm\SubscriptionsModule\Models\Extension\ExtendActualExtension;
use Crm\SubscriptionsModule\Repositories\ContentAccessRepository;
use Crm\SubscriptionsModule\Repositories\SubscriptionExtensionMethodsRepository;
use Crm\SubscriptionsModule\Repositories\SubscriptionLengthMethodsRepository;
use Crm\SubscriptionsModule\Repositories\SubscriptionTypeNamesRepository;
use Crm\SubscriptionsModule\Repositories\SubscriptionsRepository;
use Crm\SubscriptionsModule\Scenarios\IsConsecutiveSubscriptionCriteria;
use Crm\SubscriptionsModule\Seeders\ContentAccessSeeder;
use Crm\SubscriptionsModule\Seeders\SubscriptionExtensionMethodsSeeder;
use Crm\SubscriptionsModule\Seeders\SubscriptionLengthMethodSeeder;
use Crm\SubscriptionsModule\Seeders\SubscriptionTypeNamesSeeder;
use Crm\UsersModule\Models\Auth\UserManager;
use Crm\UsersModule\Repositories\UsersRepository;
use Nette\Utils\DateTime;
use PHPUnit\Framework\Attributes\DataProvider;

class IsConsecutiveSubscriptionCriteriaTest extends DatabaseTestCase
{
    use NowTrait;

    private SubscriptionsRepository $subscriptionRepository;

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

    public static function dataProvider(): array
    {
        return [
            'with following subscription' => [
                'first' => ['-28 days', 'now'],
                'second' => ['now', '+32 days'],
                'result' => true,
            ],
            'with overlapping subscription' => [
                'first' => ['-20 days', '+5 days'],
                'second' => ['now', '+25 days'],
                'result' => true,
            ],
            'with overlapping subscription after current' => [
                'first' => ['-20 days', '+40 days'],
                'second' => ['now', '+25 days'],
                'result' => true,
            ],
            'with previous subscription, 10 day gap' => [
                'first' => ['-38 days', '-10 days'],
                'second' => ['now', '+30 days'],
                'result' => false,
            ],
            'no previous subscription' => [
                'first' => null,
                'second' => ['now', '+30 days'],
                'result' => false,
            ],
            'NEGATION: with following subscription' => [
                'first' => ['-28 days', '+2 days'],
                'second' => ['+2 days', '+32 days'],
                'result' => false,
                'negation' => true,
            ],
            'NEGATION: with overlapping subscription' => [
                'first' => ['-20 days', '+5 days'],
                'second' => ['now', '+25 days'],
                'result' => false,
                'negation' => true,
            ],
            'NEGATION: with overlapping subscription after current' => [
                'first' => ['-20 days', '+40 days'],
                'second' => ['now', '+25 days'],
                'result' => false,
                'negation' => true,
            ],
            'NEGATION: with previous subscription, 10 day gap' => [
                'first' => ['-38 days', '-10 days'],
                'second' => ['now', '+30 days'],
                'result' => true,
                'negation' => true,
            ],
            'NEGATION: no previous subscription' => [
                'first' => null,
                'second' => ['now', '+30 days'],
                'result' => true,
                'negation' => true,
            ],
        ];
    }

    #[DataProvider('dataProvider')]
    public function testScenarios(?array $first, array $second, bool $result, bool $negation = false): void
    {
        $this->setNow(new DateTime());

        if ($first) {
            $this->getSubscriptionWithConditionSelection($first[0], $first[1]);
        }

        [$subscriptionSelection, $subscriptionRow] = $this->getSubscriptionWithConditionSelection($second[0], $second[1]);

        $criteria = $this->inject(IsConsecutiveSubscriptionCriteria::class);
        $values = (object)['selection' => !$negation];

        $this->assertTrue(
            $criteria->addConditions($subscriptionSelection, [IsConsecutiveSubscriptionCriteria::KEY => $values], $subscriptionRow)
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
                ->setExtensionMethod(ExtendActualExtension::METHOD_CODE)
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
            $startTime === 'now' ? $this->getNow() : DateTime::from($startTime),
            $endTime === 'now' ? $this->getNow() : DateTime::from($endTime)
        );

        $subscriptionSelection = $this->subscriptionRepository->getTable()
            ->where(['subscriptions.id' => $subscriptionRow]);

        return [$subscriptionSelection, $subscriptionRow];
    }
}
