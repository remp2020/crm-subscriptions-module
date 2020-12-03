<?php

namespace Crm\SubscriptionsModule\Populator;

use Crm\ApplicationModule\Populator\AbstractPopulator;

class SubscriptionsPopulator extends AbstractPopulator
{
    /**
     * @param \Symfony\Component\Console\Helper\ProgressBar $progressBar
     */
    public function seed($progressBar)
    {
        $subscriptions = $this->database->table('subscriptions');
        for ($i = 0; $i < $this->count; $i++) {
            $user = $this->getRecord('users');
            $startTime = $this->faker->dateTimeBetween('-1 years');
            $endTime = $this->faker->dateTimeBetween($startTime, '+2 months');
            $subscriptions->insert([
                'user_id' => $user->id,
                'subscription_type_id' => $this->getRecord('subscription_types'),
                'start_time' => $startTime,
                'end_time' => $endTime,
                'type' => $this->getRecord('subscription_type_names')->type,
                'created_at' => $this->faker->dateTimeBetween('-1 years'),
                'modified_at' => $this->faker->dateTimeBetween('-1 years'),
                'length' => $this->faker->randomDigit(),
            ]);

            $progressBar->advance();
        }
    }
}
