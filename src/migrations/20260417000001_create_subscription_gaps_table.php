<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateSubscriptionGapsTable extends AbstractMigration
{
    public function change(): void
    {
        $this->table('subscription_gaps')
            ->addColumn('user_id', 'integer', ['null' => false])
            ->addColumn('gap_start', 'datetime', ['null' => false])
            ->addColumn('gap_end', 'datetime', ['null' => false])
            ->addColumn('gap_length_days', 'integer', ['null' => false])
            ->addColumn('created_at', 'datetime', ['null' => false])
            ->addIndex(['user_id'])
            ->addIndex(['user_id', 'gap_end'])
            ->addForeignKey('user_id', 'users', 'id', ['delete' => 'CASCADE'])
            ->create();
    }
}
