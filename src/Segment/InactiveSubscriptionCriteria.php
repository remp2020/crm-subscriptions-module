<?php

namespace Crm\SubscriptionsModule\Segment;

use Crm\ApplicationModule\Criteria\CriteriaInterface;
use Crm\SegmentModule\Models\Criteria\Fields;
use Crm\SegmentModule\Models\Params\DateTimeParam;
use Crm\SegmentModule\Models\Params\NumberArrayParam;
use Crm\SegmentModule\Models\Params\ParamsBag;
use Crm\SegmentModule\Models\Params\StringArrayParam;
use Crm\SubscriptionsModule\Repositories\ContentAccessRepository;
use Crm\SubscriptionsModule\Repositories\SubscriptionTypesRepository;
use Crm\SubscriptionsModule\Repositories\SubscriptionsRepository;

class InactiveSubscriptionCriteria implements CriteriaInterface
{
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
        return "Inactive subscription";
    }

    public function category(): string
    {
        return "Subscription";
    }

    public function params(): array
    {
        return [
            new DateTimeParam('inactive_at', "Inactive at", "Filter only users who don't have a subscription within selected period", false),
            new StringArrayParam('contains', "Content types", "Users who don't have access to all selected content types", false, null, null, $this->contentAccessRepository->all()->fetchPairs(null, 'name')),
            new StringArrayParam('type', "Types of subscription", "Users who don't have access to all selected types of subscription", false, null, null, array_keys($this->subscriptionsRepository->availableTypes())),
            new NumberArrayParam('subscription_type', "Subscription types", "Users who don't have access to all selected subscription types", false, null, null, $this->subscriptionTypesRepository->all()->fetchPairs('id', 'name')),
        ];
    }

    public function join(ParamsBag $params): string
    {
        $where = [];

        if ($params->has('inactive_at')) {
            $where[] = " users.id NOT IN (SELECT user_id FROM subscriptions WHERE " .
                implode(" AND ", $params->datetime('inactive_at')->inverse()->escapedConditions('subscriptions.start_time', 'subscriptions.end_time')) .
                ")";
        }

        if ($params->has('contains')) {
            $values = $params->stringArray('contains')->escapedString();
            $where[] = " content_access.name NOT IN ({$values}) ";
        }

        if ($params->has('type')) {
            $values = $params->stringArray('type')->escapedString();
            $where[] = " subscriptions.type NOT IN ({$values}) ";
        }

        if ($params->has('subscription_type')) {
            $values = $params->numberArray('subscription_type')->escapedString();
            $where[] = " subscription_types.id NOT IN ({$values}) ";
        }

        return "SELECT DISTINCT(users.id) AS id, " . Fields::formatSql($this->fields()) . "
          FROM users
          LEFT JOIN subscriptions ON subscriptions.user_id = users.id
          LEFT JOIN subscription_types ON subscription_types.id = subscriptions.subscription_type_id
          LEFT JOIN subscription_type_content_access ON subscription_type_content_access.subscription_type_id = subscription_types.id
          LEFT JOIN content_access ON content_access.id = subscription_type_content_access.content_access_id
          WHERE " . implode(" AND ", $where);
    }

    public function title(ParamsBag $params): string
    {
        $title = '';
        if ($params->has('inactive_at') || $params->has('contains') || $params->has('type') || $params->has('subscription_type') || $params->has('is_recurrent')) {
            if ($params->has('inactive_at')) {
                $title .= ' inactive subscription' . $params->datetime('inactive_at')->title('subscriptions.start_time', 'subscriptions.end_time');
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
        }

        return $title;
    }

    public function fields(): array
    {
        return [
            'users.id' => 'user_id',
        ];
    }
}
