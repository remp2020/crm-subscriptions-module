<?php
declare(strict_types=1);

namespace Crm\SubscriptionsModule\DataProviders;

use Crm\ApplicationModule\Helpers\LocalizedDateHelper;
use Crm\ApplicationModule\Models\DataProvider\AuditLogHistoryDataProviderInterface;
use Crm\ApplicationModule\Models\DataProvider\AuditLogHistoryDataProviderItem;
use Crm\ApplicationModule\Models\DataProvider\AuditLogHistoryItemChangeIndicatorEnum;
use Crm\ApplicationModule\Repositories\AuditLogRepository;
use Crm\SubscriptionsModule\Repositories\SubscriptionTypesRepository;
use Crm\SubscriptionsModule\Repositories\SubscriptionsRepository;
use Nette\Application\LinkGenerator;
use Nette\Utils\Json;

class SubscriptionAuditLogHistoryDataProvider implements AuditLogHistoryDataProviderInterface
{
    private const WATCHED_COLUMNS = [
        'subscription_type_id',
        'start_time',
        'end_time',
        'note',
        'address_id',
    ];

    public function __construct(
        private readonly AuditLogRepository $auditLogRepository,
        private readonly SubscriptionTypesRepository $subscriptionTypesRepository,
        private readonly SubscriptionsRepository $subscriptionsRepository,
        private readonly LinkGenerator $linkGenerator,
        private readonly LocalizedDateHelper $localizedDateHelper,
    ) {
    }

    public function provide(string $tableName, string $signature): array
    {
        if ($tableName !== 'subscriptions') {
            return [];
        }

        $history = $this->auditLogRepository->getByTableAndSignature($tableName, $signature)
            ->order('created_at ASC, id ASC')
            ->fetchAll();

        $results = [];
        foreach ($history as $item) {
            $itemKey = strval($item->created_at) . $item->user?->id;
            $auditLogHistoryDataProviderItem = $results[$itemKey] ?? new AuditLogHistoryDataProviderItem(
                $item->created_at,
                $item->operation,
                $item->user,
            );

            $changes = Json::decode($item->data, true);

            // handle subscription type change
            if (isset($changes['from']['subscription_type_id']) && isset($changes['to']['subscription_type_id'])) {
                $auditLogHistoryDataProviderItem->addMessage(
                    'subscriptions.data_provider.payment_audit_log_history.subscription_type_change',
                    [
                        'from' => $this->subscriptionTypesRepository->find(intval($changes['from']['subscription_type_id']))->name,
                        'to' => $this->subscriptionTypesRepository->find(intval($changes['to']['subscription_type_id']))->name,
                    ],
                );
            }

            // handle start time change
            if (isset($changes['from']['start_time'])) {
                $auditLogHistoryDataProviderItem->addMessage(
                    'subscriptions.data_provider.payment_audit_log_history.start_time_change',
                    [
                        'from' => $this->localizedDateHelper->process($changes['from']['start_time']),
                        'to' => $this->localizedDateHelper->process($changes['to']['start_time']),
                    ],
                );
            }

            // handle end time change
            if (isset($changes['from']['end_time'])) {
                $auditLogHistoryDataProviderItem->addMessage(
                    'subscriptions.data_provider.payment_audit_log_history.end_time_change',
                    [
                        'from' => $this->localizedDateHelper->process($changes['from']['end_time']),
                        'to' => $this->localizedDateHelper->process($changes['to']['end_time']),
                    ],
                );
            }

            // handle end time change
            if (isset($changes['from']['length'])) {
                $auditLogHistoryDataProviderItem->addMessage(
                    'subscriptions.data_provider.payment_audit_log_history.length_change',
                    [
                        'from' => $changes['from']['length'],
                        'to' => $changes['to']['length'],
                    ],
                );
            }

            // handle note change
            if (isset($changes['to']['note'])) {
                $auditLogHistoryDataProviderItem->addMessage(
                    'subscriptions.data_provider.payment_audit_log_history.note_change',
                    [
                        'note' => $changes['to']['note'],
                    ],
                );
            }

            // if there are no messages, but we have changes, add a default message
            if ($item->operation === 'update' && empty($auditLogHistoryDataProviderItem->getMessages())) {
                // Filter only watched columns
                $changedColumns = array_intersect(array_keys($changes['to']), self::WATCHED_COLUMNS);
                if (!empty($changedColumns)) {
                    $auditLogHistoryDataProviderItem->addMessage(
                        'subscriptions.data_provider.payment_audit_log_history.columns_changed',
                        [
                            'columns' => implode(', ', $changedColumns),
                        ],
                    );
                }
            }

            $results[$itemKey] = $auditLogHistoryDataProviderItem;
        }

        // handle subscription transfer
        $subscription = $this->subscriptionsRepository->find(intval($signature));
        $transferMeta = $subscription->related('subscriptions_meta')
            ->where('key', SubscriptionTransferDataProviderInterface::META_KEY_TRANSFERRED_FROM_USER)
            ->fetch();
        if ($transferMeta) {
            $auditLogHistoryDataProviderItem = new AuditLogHistoryDataProviderItem(
                $transferMeta->created_at,
                AuditLogRepository::OPERATION_UPDATE,
                null,
                AuditLogHistoryItemChangeIndicatorEnum::Info,
            );
            $auditLogHistoryDataProviderItem->addMessage(
                'subscriptions.data_provider.payment_audit_log_history.subscription_transfer',
                [
                    'user_id' => $transferMeta->value,
                    'link' => $this->linkGenerator->link(':Users:UsersAdmin:show', ['id' => $transferMeta->value]),
                ],
            );
            $results[] = $auditLogHistoryDataProviderItem;
        }

        return array_values($results);
    }
}
