<?php
declare(strict_types=1);

use Phinx\Db\Adapter\MysqlAdapter;
use Phinx\Migration\AbstractMigration;

final class AddTrialPeriodsIntoSubscriptionTypes extends AbstractMigration
{
    public function up(): void
    {
        $this->table('subscription_types')
            ->addColumn('trial_periods', 'integer', [
                'null' => false,
                'default' => 0, // zero trials
                'signed' => false,
                'limit' => MysqlAdapter::INT_TINY, // 255 trials should be enough
                'after' => 'next_subscription_type_id',
            ])
            ->update();

        // If next_subscription_type was set, second subscription would be created with it
        // so subscriber would get ONE trial period. This update should keep this behaviour
        // same (so new feature "trial periods" is not breaking change).
        $this->execute(<<<SQL
            UPDATE `subscription_types`
            SET `trial_periods` = 1
            WHERE `next_subscription_type_id` IS NOT NULL;
        SQL);
    }

    public function down(): void
    {
        $this->table('subscription_types')
            ->removeColumn('trial_periods')
            ->update();
    }
}
