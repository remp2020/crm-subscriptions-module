<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class FixZeroTrialPeriodsForSubscriptionTypesWithNextSubscriptionTypeIdFilled extends AbstractMigration
{
    public function up(): void
    {
        // If a subscription type has a next_subscription_type_id set, it must have
        // at least 1 trial period. Fix rows where trial_periods is 0 or NULL.
        $this->execute(
            "UPDATE `subscription_types` SET `trial_periods` = 1
             WHERE `next_subscription_type_id` IS NOT NULL
             AND (`trial_periods` = 0 OR `trial_periods` IS NULL)"
        );

        $this->execute(
            "UPDATE `subscription_types` SET `trial_periods` = NULL
             WHERE `next_subscription_type_id` IS NULL
             AND (`trial_periods` = 0)"
        );
    }

    public function down(): void
    {
        $this->output->writeln('This is data migration. Down migration is not available.');
    }
}
