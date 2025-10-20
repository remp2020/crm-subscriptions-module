<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;
use Phinx\Db\Adapter\MysqlAdapter;

final class ChangeSubscriptionTypesTrialPeriodsColumnToNullable extends AbstractMigration
{
    public function up(): void
    {
        $this->table('subscription_types')
            ->changeColumn('trial_periods', 'integer', [
                'null' => true,
                'default' => null,
                'signed' => false,
                'limit' => MysqlAdapter::INT_TINY,
            ])
            ->update();
    }

    public function down(): void
    {
        $this->table('subscription_types')
            ->changeColumn('trial_periods', 'integer', [
                'null' => false,
                'default' => 0,
                'signed' => false,
                'limit' => MysqlAdapter::INT_TINY,
            ])
            ->update();
    }
}
