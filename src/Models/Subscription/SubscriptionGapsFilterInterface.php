<?php
declare(strict_types=1);

namespace Crm\SubscriptionsModule\Models\Subscription;

use Nette\Database\Table\Selection;

/**
 * Limits the subscriptions {@see \Crm\SubscriptionsModule\Repositories\SubscriptionGapsRepository} builds the
 * coverage intervals (and so the gaps) from. Subscriptions filtered out do not cover the user.
 */
interface SubscriptionGapsFilterInterface
{
    public function filter(Selection $subscriptions): Selection;
}
