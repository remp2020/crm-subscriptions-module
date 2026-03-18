<?php

namespace Crm\SubscriptionsModule\Segment;

class UserWithSubscriptionCriteria extends BaseWithSubscriptionCriteria
{
    protected $tableField = 'subscriptions.user_id';
}
