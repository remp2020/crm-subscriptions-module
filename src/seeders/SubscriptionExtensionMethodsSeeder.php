<?php

namespace Crm\SubscriptionsModule\Seeders;

use Crm\ApplicationModule\Seeders\ISeeder;
use Crm\SubscriptionsModule\Extension\ExtendActualExtension;
use Crm\SubscriptionsModule\Extension\ExtendSameActualExtension;
use Crm\SubscriptionsModule\Extension\ExtendSameTypeExtension;
use Crm\SubscriptionsModule\Extension\StartNowExtension;
use Crm\SubscriptionsModule\Repository\SubscriptionExtensionMethodsRepository;
use Symfony\Component\Console\Output\OutputInterface;

class SubscriptionExtensionMethodsSeeder implements ISeeder
{
    private $subscriptionExtensionMethodsRepository;

    public function __construct(SubscriptionExtensionMethodsRepository $subscriptionExtensionMethodsRepository)
    {
        $this->subscriptionExtensionMethodsRepository = $subscriptionExtensionMethodsRepository;
    }

    public function seed(OutputInterface $output)
    {
        $method = ExtendActualExtension::METHOD_CODE;
        if (!$this->subscriptionExtensionMethodsRepository->exists($method)) {
            $this->subscriptionExtensionMethodsRepository->add(
                $method,
                ExtendActualExtension::METHOD_NAME,
                'Put new subscription after actual subscription or starts now',
                100
            );
            $output->writeln("  <comment>* subscription extension method <info>{$method}</info> created</comment>");
        } else {
            $output->writeln("  * subscription extension method <info>{$method}</info> exists");
        }

        $method = ExtendSameActualExtension::METHOD_CODE;
        if (!$this->subscriptionExtensionMethodsRepository->exists($method)) {
            $this->subscriptionExtensionMethodsRepository->add(
                $method,
                ExtendSameActualExtension::METHOD_NAME,
                'Put new subscription after actual subscription of the same type or starts now',
                100
            );
            $output->writeln("  <comment>* subscription extension method <info>{$method}</info> created</comment>");
        } else {
            $output->writeln("  * subscription extension method <info>{$method}</info> exists");
        }

        $method = StartNowExtension::METHOD_CODE;
        if (!$this->subscriptionExtensionMethodsRepository->exists($method)) {
            $this->subscriptionExtensionMethodsRepository->add(
                $method,
                StartNowExtension::METHOD_NAME,
                'Begins immediately regardless actual subscription',
                200
            );
            $output->writeln("  <comment>* subscription extension method <info>{$method}</info> created</comment>");
        } else {
            $output->writeln("  * subscription extension method <info>{$method}</info> exists");
        }

        $method = ExtendSameTypeExtension::METHOD_CODE;
        if (!$this->subscriptionExtensionMethodsRepository->exists($method)) {
            $this->subscriptionExtensionMethodsRepository->add(
                $method,
                ExtendSameTypeExtension::METHOD_NAME,
                'Put new subscription after last subscription of the same type or use extend_actual method',
                100
            );
            $output->writeln("  <comment>* subscription extension method <info>{$method}</info> created</comment>");
        } else {
            $output->writeln("  * subscription extension method <info>{$method}</info> exists");
        }
    }
}
