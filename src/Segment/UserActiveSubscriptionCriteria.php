<?php

namespace Crm\SubscriptionsModule\Segment;

class UserActiveSubscriptionCriteria extends BaseActiveSubscriptionCriteria
{
    protected $tableField = 'subscriptions.user_id';
}
