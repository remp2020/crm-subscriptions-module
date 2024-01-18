<?php

namespace Crm\SubscriptionsModule\DataProviders;

use Crm\ApplicationModule\DataProvider\DataProviderException;
use Crm\SubscriptionsModule\Repositories\SubscriptionTypesRepository;
use Crm\UsersModule\DataProvider\FilterUserActionLogsFormDataProviderInterface;
use Crm\UsersModule\Repository\UserActionsLogRepository;
use Nette\Application\UI\Form;

class FilterUserActionLogsFormDataProvider implements FilterUserActionLogsFormDataProviderInterface
{
    private $subscriptionTypesRepository;

    private $userActionsLogRepository;

    public function __construct(
        SubscriptionTypesRepository $subscriptionTypesRepository,
        UserActionsLogRepository $userActionsLogRepository
    ) {
        $this->subscriptionTypesRepository = $subscriptionTypesRepository;
        $this->userActionsLogRepository = $userActionsLogRepository;
    }

    public function provide(array $params): Form
    {
        if (!isset($params['form'])) {
            throw new DataProviderException('form param missing');
        }

        $subscriptionTypeIds = $this->userActionsLogRepository->availableSubscriptionTypes();
        $subscriptionTypes = $this->subscriptionTypesRepository->all()->where('id', $subscriptionTypeIds)->fetchPairs('id', 'name');
        $params['form']->addSelect('subscriptionTypeId', 'Predplatné', $subscriptionTypes)->setPrompt('--');

        return $params['form'];
    }
}
