<?php

namespace Crm\SubscriptionsModule\DataProviders;

use Crm\ApplicationModule\Models\User\UserDataProviderInterface;
use Crm\ApplicationModule\Repositories\ConfigsRepository;
use Crm\SubscriptionsModule\Models\Config;
use Crm\SubscriptionsModule\Models\Subscription\StopSubscriptionHandler;
use Crm\SubscriptionsModule\Repositories\SubscriptionsRepository;
use Nette\Localization\Translator;
use Nette\Utils\DateTime;
use Tracy\Debugger;

class SubscriptionsUserDataProvider implements UserDataProviderInterface
{
    private $subscriptionsRepository;

    private $translator;

    private $configRepository;

    private StopSubscriptionHandler $stopSubscriptionHandler;

    public function __construct(
        SubscriptionsRepository $subscriptionsRepository,
        Translator $translator,
        ConfigsRepository $configRepository,
        StopSubscriptionHandler $stopSubscriptionHandler
    ) {
        $this->subscriptionsRepository = $subscriptionsRepository;
        $this->translator = $translator;
        $this->configRepository = $configRepository;
        $this->stopSubscriptionHandler = $stopSubscriptionHandler;
    }

    public static function identifier(): string
    {
        return 'subscriptions';
    }

    public function data($userId): ?array
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
                'id' => $subscription->id,
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
        $now = new DateTime();
        $subscriptions = $this->subscriptionsRepository->userSubscriptions($userId)
            ->where('end_time > ?', $now);

        foreach ($subscriptions as $subscription) {
            $this->stopSubscriptionHandler->stopSubscription(
                subscription: $subscription,
                expiredByAdmin: true,
            );
        }
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
