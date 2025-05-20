<?php

namespace Crm\SubscriptionsModule\Seeders;

use Crm\ApplicationModule\Seeders\ISeeder;
use Crm\SubscriptionsModule\Models\Length\CalendarDaysLengthMethod;
use Crm\SubscriptionsModule\Models\Length\FixDaysLengthMethod;
use Crm\SubscriptionsModule\Repositories\SubscriptionLengthMethodsRepository;
use Symfony\Component\Console\Output\OutputInterface;

class SubscriptionLengthMethodSeeder implements ISeeder
{
    private $subscriptionLengthMethodsRepository;

    public function __construct(SubscriptionLengthMethodsRepository $subscriptionLengthMethodsRepository)
    {
        $this->subscriptionLengthMethodsRepository = $subscriptionLengthMethodsRepository;
    }

    public function seed(OutputInterface $output)
    {
        $method = FixDaysLengthMethod::METHOD_CODE;
        if (!$this->subscriptionLengthMethodsRepository->exists($method)) {
            $this->subscriptionLengthMethodsRepository->add(
                $method,
                'Fixed days',
                'Calculate subscription length based on fixed days values in subscription type',
                100,
            );
            $output->writeln("  <comment>* subscription extension method <info>{$method}</info> created</comment>");
        } else {
            $output->writeln("  * subscription extension method <info>{$method}</info> exists");
        }

        $method = CalendarDaysLengthMethod::METHOD_CODE;
        if (!$this->subscriptionLengthMethodsRepository->exists($method)) {
            $this->subscriptionLengthMethodsRepository->add(
                $method,
                'Calendar days',
                'Calculate subscription length based on calendar days (days in subscription month)',
                200,
            );
            $output->writeln("  <comment>* subscription extension method <info>{$method}</info> created</comment>");
        } else {
            $output->writeln("  * subscription extension method <info>{$method}</info> exists");
        }
    }
}
