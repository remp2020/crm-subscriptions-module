<?php

namespace Crm\SubscriptionsModule\Seeders;

use Crm\ApplicationModule\Seeders\ISeeder;
use Crm\SubscriptionsModule\Models\Builder\SubscriptionTypeBuilder;
use Crm\SubscriptionsModule\Repositories\SubscriptionTypesRepository;
use Symfony\Component\Console\Output\OutputInterface;

class TestSeeder implements ISeeder
{
    public const SUBSCRIPTION_TYPE_WEB_MONTH = 'test_web_month';
    public const SUBSCRIPTION_TYPE_WEB_YEAR = 'test_web_year';

    public function __construct(
        private SubscriptionTypesRepository $subscriptionTypesRepository,
        private SubscriptionTypeBuilder $subscriptionTypeBuilder,
    ) {
    }

    public function seed(OutputInterface $output)
    {
        if (!$this->subscriptionTypesRepository->exists(self::SUBSCRIPTION_TYPE_WEB_YEAR)) {
            $subscriptionType = $this->subscriptionTypeBuilder->createNew()
                ->setNameAndUserLabel('Upgrade tests yearly')
                ->setCode(self::SUBSCRIPTION_TYPE_WEB_YEAR)
                ->setPrice(123.45)
                ->setLength(365)
                ->setSorting(20)
                ->setActive(true)
                ->setVisible(true)
                ->setContentAccessOption('web')
                ->save();
            $output->writeln("  <comment>* subscription type <info> " . self::SUBSCRIPTION_TYPE_WEB_YEAR ." </info> created</comment>");
        } else {
            $output->writeln("  * subscription type <info>" . self::SUBSCRIPTION_TYPE_WEB_YEAR . "</info> exists");
        }

        if (!$this->subscriptionTypesRepository->exists(self::SUBSCRIPTION_TYPE_WEB_MONTH)) {
            $subscriptionType = $this->subscriptionTypeBuilder->createNew()
                ->setNameAndUserLabel('Upgrade tests yearly')
                ->setCode(self::SUBSCRIPTION_TYPE_WEB_MONTH)
                ->setPrice(12.34)
                ->setLength(31)
                ->setSorting(20)
                ->setActive(true)
                ->setVisible(true)
                ->setContentAccessOption('web')
                ->save();
            $output->writeln("  <comment>* subscription type <info>" . self::SUBSCRIPTION_TYPE_WEB_MONTH . "</info> created</comment>");
        } else {
            $output->writeln("  * subscription type <info>" . self::SUBSCRIPTION_TYPE_WEB_MONTH . "</info> exists");
        }
    }
}
