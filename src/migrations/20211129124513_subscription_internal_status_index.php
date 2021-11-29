<?php

use Phinx\Migration\AbstractMigration;

class SubscriptionInternalStatusIndex extends AbstractMigration
{
    public function change()
    {
        $this->table('subscriptions')
            ->addIndex('internal_status')
            ->update();
    }
}
