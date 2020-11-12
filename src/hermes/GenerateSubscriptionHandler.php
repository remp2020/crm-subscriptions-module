<?php

namespace Crm\SubscriptionsModule\Hermes;

use Crm\SubscriptionsModule\Generator\SubscriptionsGenerator;
use Crm\SubscriptionsModule\Generator\SubscriptionsParams;
use Crm\SubscriptionsModule\Repository\SubscriptionTypesRepository;
use Crm\UsersModule\Auth\UserManager;
use Nette\Utils\DateTime;
use Tomaj\Hermes\Handler\HandlerInterface;
use Tomaj\Hermes\MessageInterface;

class GenerateSubscriptionHandler implements HandlerInterface
{
    private $userManager;

    private $subscriptionsGenerator;

    private $subscriptionTypesRepository;

    public function __construct(
        UserManager $userManager,
        SubscriptionsGenerator $subscriptionsGenerator,
        SubscriptionTypesRepository $subscriptionTypesRepository
    ) {
        $this->userManager = $userManager;
        $this->subscriptionsGenerator = $subscriptionsGenerator;
        $this->subscriptionTypesRepository = $subscriptionTypesRepository;
    }

    public function handle(MessageInterface $message): bool
    {
        $toRegister = $message->getPayload()['register'];
        $toSubscribe = $message->getPayload()['subscribe'];

        foreach ($toRegister as $record) {
            $user = $this->userManager->loadUserByEmail($record['email']);
            if (!$user) {
                $this->userManager->addNewUser($record['email'], $record['send_email'], $record['source'], null, $record['check_email']);
            }
        }

        foreach ($toSubscribe as $record) {
            $subscriptionType = $this->subscriptionTypesRepository->find($record['subscription_type_id']);
            $user = $this->userManager->loadUserByEmail($record['email']);

            $this->subscriptionsGenerator->generate(
                new SubscriptionsParams(
                    $subscriptionType,
                    $user,
                    $record['type'],
                    DateTime::from($record['start_time']),
                    DateTime::from($record['end_time']),
                    $record['is_paid'],
                    $record['note'] ?? null
                ),
                1
            );
        }

        return true;
    }
}
