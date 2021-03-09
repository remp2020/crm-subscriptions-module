<?php

namespace Crm\SubscriptionsModule\Measurements;

use Crm\ApplicationModule\Models\Measurements\BaseMeasurement;
use Crm\ApplicationModule\Models\Measurements\Criteria;
use Crm\ApplicationModule\Models\Measurements\Point;
use Crm\ApplicationModule\Models\Measurements\Series;

class ActiveSubscriptionsMeasurement extends BaseMeasurement
{
    public const CODE = 'subscriptions.active';

    public function calculate(Criteria $criteria): Series
    {
        $series = $criteria->getEmptySeries();

        $date = clone $criteria->getFrom();
        while ($date <= $criteria->getTo()) {
            $next = $criteria->getAggregation()->nextDate($date);

            $query = "
                SELECT COUNT(id) AS count
                FROM subscriptions
                WHERE ?
            ";

            $rows = $this->db()->query(
                $query,
                [
                    'start_time <=' => $next,
                    'end_time >=' => $date,
                ],
            );
            foreach ($rows as $row) {
                $point = new Point($criteria->getAggregation(), $row->count, clone $date);
                $series->setPoint($point);
            }
            $date = $next;
        }
        return $series;
    }
}
