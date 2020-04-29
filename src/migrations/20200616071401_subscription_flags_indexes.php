<?php

use Phinx\Migration\AbstractMigration;

class SubscriptionFlagsIndexes extends AbstractMigration
{
    public function change()
    {
        $this->table('subscriptions')
            ->addIndex('is_paid')
            ->addIndex('is_recurrent')
            ->update();
    }
}
