<?php

namespace Crm\SubscriptionsModule\User;

use Crm\ApplicationModule\Config\Repository\ConfigsRepository;
use Crm\ApplicationModule\User\UserDataProviderInterface;
use Crm\SubscriptionsModule\Model\Config;
use Crm\SubscriptionsModule\Repository\SubscriptionsRepository;
use Nette\Localization\ITranslator;
use Nette\Utils\DateTime;
use Tracy\Debugger;

class SubscriptionsUserDataProvider implements UserDataProviderInterface
{
    private $subscriptionsRepository;

    private $translator;

    private $configRepository;

    public function __construct(
        SubscriptionsRepository $subscriptionsRepository,
        ITranslator $translator,
        ConfigsRepository $configRepository
    ) {
        $this->subscriptionsRepository = $subscriptionsRepository;
        $this->translator = $translator;
        $this->configRepository = $configRepository;
    }

    public static function identifier(): string
    {
        return 'subscriptions';
    }

    public function data($userId)
    {
        $subscriptions = $this->subscriptionsRepository->userSubscriptions($userId)->where(['end_time > ?' => new DateTime()]);

        $result = [];
        foreach ($subscriptions as $subscription) {
            $types = [];
            $subscriptionTypes = $subscription->subscription_type->related('subscription_type_content_access')->order('content_access.sorting');
            foreach ($subscriptionTypes as $contentAccess) {
                $types[] = $contentAccess->content_access->name;
            }
            $result[] = [
                'start_time' => $subscription->start_time->getTimestamp(),
                'end_time' => $subscription->end_time->getTimestamp(),
                'code' => $subscription->subscription_type->code,
                'is_recurrent' => (bool) $subscription->is_recurrent,
                'types' => $types,
            ];
        }

        return $result;
    }

    public function download($userId)
    {
        $subscriptions = $this->subscriptionsRepository->userSubscriptions($userId);

        $result = [];
        foreach ($subscriptions as $subscription) {
            $result[] = [
                'start_time' => $subscription->start_time->format(\DateTime::RFC3339),
                'end_time' => $subscription->end_time->format(\DateTime::RFC3339),
                'subscription_type' => $subscription->subscription_type->user_label,
                'type' => $subscription->type
            ];
        }

        return $result;
    }

    public function downloadAttachments($userId)
    {
        return [];
    }

    public function protect($userId): array
    {
        return [];
    }

    public function delete($userId, $protectedData = [])
    {
        return false;
    }

    public function canBeDeleted($userId): array
    {
        $configRow = $this->configRepository->loadByName(Config::BLOCK_ANONYMIZATION);
        if ($configRow && $configRow->value) {
            $configRow = $this->configRepository->loadByName(Config::BLOCK_ANONYMIZATION_WITHIN_DAYS);
            if ($configRow && is_numeric($configRow->value) && $configRow->value >= 0) {
                $deleteThreshold = new DateTime("-{$configRow->value} days");
            } elseif (empty($configRow->value) === true) {
                $deleteThreshold = new DateTime();
            } else {
                Debugger::log("Unexpected value for config option (" . Config::BLOCK_ANONYMIZATION_WITHIN_DAYS . "): {$configRow->value}");
                return [false, $this->translator->translate('subscriptions.data_provider.delete.unexpected_configuration_value')];
            }

            if ($this->subscriptionsRepository->hasSubscriptionEndAfter($userId, $deleteThreshold)) {
                return [false, $this->translator->translate('subscriptions.data_provider.delete.active_subscription')];
            }
        }

        return [true, null];
    }
}
