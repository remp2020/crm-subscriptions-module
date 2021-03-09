<?php

namespace Crm\SubscriptionsModule\Seeders;

use Crm\ApplicationModule\Models\Measurements\Repository\MeasurementsRepository;
use Crm\ApplicationModule\Seeders\ISeeder;
use Crm\ApplicationModule\Seeders\MeasurementsTrait;
use Crm\SubscriptionsModule\Measurements\ActivePayingSubscribersMeasurement;
use Crm\SubscriptionsModule\Measurements\ActiveSubscribersMeasurement;
use Crm\SubscriptionsModule\Measurements\ActiveSubscriptionsMeasurement;
use Crm\SubscriptionsModule\Measurements\EndedSubscriptionsMeasurement;
use Crm\SubscriptionsModule\Measurements\StartedSubscriptionsMeasurement;
use Symfony\Component\Console\Output\OutputInterface;

class MeasurementsSeeder implements ISeeder
{
    use MeasurementsTrait;

    private MeasurementsRepository $measurementsRepository;

    public function __construct(MeasurementsRepository $measurementsRepository)
    {
        $this->measurementsRepository = $measurementsRepository;
    }

    public function seed(OutputInterface $output)
    {
        $this->addMeasurement(
            $output,
            ActiveSubscriptionsMeasurement::CODE,
            'subscriptions.measurements.active_subscriptions.title',
            'subscriptions.measurements.active_subscriptions.description',
        );
        $this->addMeasurement(
            $output,
            ActiveSubscribersMeasurement::CODE,
            'subscriptions.measurements.active_subscribers.title',
            'subscriptions.measurements.active_subscribers.description',
        );
        $this->addMeasurement(
            $output,
            ActivePayingSubscribersMeasurement::CODE,
            'subscriptions.measurements.active_paying_subscribers.title',
            'subscriptions.measurements.active_paying_subscribers.description',
        );
        $this->addMeasurement(
            $output,
            StartedSubscriptionsMeasurement::CODE,
            'subscriptions.measurements.started_subscriptions.title',
            'subscriptions.measurements.started_subscriptions.description',
        );
        $this->addMeasurement(
            $output,
            EndedSubscriptionsMeasurement::CODE,
            'subscriptions.measurements.ended_subscriptions.title',
            'subscriptions.measurements.ended_subscriptions.description',
        );
    }
}
