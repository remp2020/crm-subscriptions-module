<?php

namespace Crm\SubscriptionsModule\DataProviders;

use Crm\ApplicationModule\Models\DataProvider\DataProviderException;
use Crm\ApplicationModule\UI\Form;
use Crm\SubscriptionsModule\Repositories\SubscriptionTypesRepository;
use Crm\UsersModule\DataProviders\FilterUserActionLogsFormDataProviderInterface;
use Crm\UsersModule\Repositories\UserActionsLogRepository;

class FilterUserActionLogsFormDataProvider implements FilterUserActionLogsFormDataProviderInterface
{
    private $subscriptionTypesRepository;

    private $userActionsLogRepository;

    public function __construct(
        SubscriptionTypesRepository $subscriptionTypesRepository,
        UserActionsLogRepository $userActionsLogRepository,
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
