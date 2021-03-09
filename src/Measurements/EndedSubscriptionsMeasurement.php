<?php

namespace Crm\SubscriptionsModule\Measurements;

use Crm\ApplicationModule\Models\Measurements\Aggregation\DateData;
use Crm\ApplicationModule\Models\Measurements\BaseMeasurement;
use Crm\ApplicationModule\Models\Measurements\Criteria;
use Crm\ApplicationModule\Models\Measurements\Point;
use Crm\ApplicationModule\Models\Measurements\Series;

class EndedSubscriptionsMeasurement extends BaseMeasurement
{
    public const CODE = 'subscriptions.ended';

    public function calculate(Criteria $criteria): Series
    {
        $fields = $criteria->getAggregation()->select('subscriptions.end_time');
        $fieldsString = implode(',', $fields);

        $query = "
            SELECT {$fieldsString}, COUNT(*) AS count
            FROM subscriptions
            WHERE ?
            GROUP BY {$criteria->getAggregation()->group($fields)}
            ORDER BY {$criteria->getAggregation()->group($fields)}
        ";

        $series = $criteria->getEmptySeries();

        $result = $this->db()->query(
            $query,
            [
                'subscriptions.end_time <=' => $criteria->getTo(),
                'subscriptions.start_time >=' => $criteria->getFrom(),
            ],
        );
        $rows = $result->fetchAll();
        foreach ($rows as $row) {
            $point = new Point($criteria->getAggregation(), $row->count, DateData::fromRow($row)->getDateTime());
            $series->setPoint($point);
        }

        return $series;
    }
}
