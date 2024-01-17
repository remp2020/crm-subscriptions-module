<?php

namespace Crm\SubscriptionsModule\Segment;

use Crm\ApplicationModule\Criteria\CriteriaInterface;
use Crm\SegmentModule\Models\Criteria\Fields;
use Crm\SegmentModule\Models\Params\BooleanParam;
use Crm\SegmentModule\Models\Params\DateTimeParam;
use Crm\SegmentModule\Models\Params\NumberArrayParam;
use Crm\SegmentModule\Models\Params\ParamsBag;
use Crm\SegmentModule\Models\Params\StringArrayParam;
use Crm\SubscriptionsModule\Repository\ContentAccessRepository;
use Crm\SubscriptionsModule\Repository\SubscriptionTypesRepository;
use Crm\SubscriptionsModule\Repository\SubscriptionsRepository;

abstract class BaseActiveSubscriptionCriteria implements CriteriaInterface
{
    protected $tableField;

    private $contentAccessRepository;

    private $subscriptionTypesRepository;

    private $subscriptionsRepository;

    public function __construct(
        ContentAccessRepository $contentAccessRepository,
        SubscriptionsRepository $subscriptionsRepository,
        SubscriptionTypesRepository $subscriptionTypesRepository
    ) {
        $this->contentAccessRepository = $contentAccessRepository;
        $this->subscriptionTypesRepository = $subscriptionTypesRepository;
        $this->subscriptionsRepository = $subscriptionsRepository;
    }

    public function label(): string
    {
        return "Active subscription";
    }

    public function category(): string
    {
        return "Subscription";
    }

    public function params(): array
    {
        return [
            new DateTimeParam('active_at', "Active at", "Filter only subscriptions active within selected period", false),
            new StringArrayParam('contains', "Content types", "Users who have access to selected content types", false, null, null, $this->contentAccessRepository->all()->fetchPairs(null, 'name')),
            new StringArrayParam('type', "Types of subscription", "Users who have access to selected types of subscription", false, null, null, array_keys($this->subscriptionsRepository->availableTypes())),
            new NumberArrayParam('subscription_type', "Subscription types", "Users who have access to selected subscription types", false, null, null, $this->subscriptionTypesRepository->all()->fetchPairs('id', 'name')),
            new BooleanParam('is_recurrent', "Recurrent subscriptions", "Users who had at least one recurrent subscription"),
        ];
    }

    public function join(ParamsBag $params): string
    {
        $where = [];

        if ($params->has('active_at')) {
            $where = array_merge($where, $params->datetime('active_at')->escapedConditions('subscriptions.start_time', 'subscriptions.end_time'));
        }

        if ($params->has('contains')) {
            $values = $params->stringArray('contains')->escapedString();
            $where[] = " content_access.name IN ({$values}) ";
        }

        if ($params->has('type')) {
            $values = $params->stringArray('type')->escapedString();
            $where[] = " subscriptions.type IN ({$values}) ";
        }

        if ($params->has('subscription_type')) {
            $values = $params->numberArray('subscription_type')->escapedString();
            $where[] = " subscription_types.id IN ({$values}) ";
        }

        if ($params->has('is_recurrent')) {
            $where[] = " subscriptions.is_recurrent = {$params->boolean('is_recurrent')->number()} ";
        }

        return "SELECT DISTINCT({$this->tableField}) AS id, " . Fields::formatSql($this->fields()) . "
          FROM subscriptions
          INNER JOIN subscription_types ON subscription_types.id = subscriptions.subscription_type_id
          INNER JOIN subscription_type_content_access ON subscription_type_content_access.subscription_type_id = subscription_types.id
          INNER JOIN content_access ON content_access.id = subscription_type_content_access.content_access_id
          WHERE " . implode(" AND ", $where);
    }

    public function title(ParamsBag $params): string
    {
        $title = '';
        if ($params->has('active_at') || $params->has('contains') || $params->has('type') || $params->has('subscription_type') || $params->has('is_recurrent')) {
            if ($params->has('active_at')) {
                $title .= ' active subscription' . $params->datetime('active_at')->title('subscriptions.start_time', 'subscriptions.end_time');
            } else {
                $title .= ' any subscription';
            }

            if ($params->has('contains')) {
                $title .= ' contains ' . $params->stringArray('contains')->escapedString();
            }

            if ($params->has('type')) {
                $title .= ' type ' . $params->stringArray('type')->escapedString();
            }

            if ($params->has('subscription_type')) {
                $title .= ' ' . $params->stringArray('subscription_type')->escapedString();
            }

            if ($params->has('is_recurrent')) {
                if ($params->boolean('is_recurrent')->isTrue()) {
                    $title .= ' recurrent ';
                } else {
                    $title .= ' not recurrent ';
                }
            }
        }

        return $title;
    }

    public function fields(): array
    {
        return [
            'subscriptions.id' => 'subscription_id',
            'subscriptions.start_time' => 'start_time',
            'subscriptions.end_time' => 'end_time',
            'subscriptions.type' => 'type',
            'subscription_types.id' => 'subscription_type_id',
            'subscription_types.length' => 'subscription_type_length',
            'subscription_types.name' => 'subscription_type_name',
            'subscription_types.price' => 'subscription_type_price',
        ];
    }
}
