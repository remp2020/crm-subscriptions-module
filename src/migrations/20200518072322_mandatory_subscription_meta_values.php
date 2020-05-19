<?php

use Phinx\Migration\AbstractMigration;

class MandatorySubscriptionMetaValues extends AbstractMigration
{
    public function change()
    {
        $this->query('DELETE FROM subscriptions_meta WHERE value IS NULL');
        $this->table('subscriptions_meta')
            ->changeColumn('value', 'string', ['null' => false])
            ->update();

        $this->query('DELETE FROM subscription_types_meta WHERE value IS NULL');
        $this->table('subscription_types_meta')
            ->changeColumn('value', 'string', ['null' => false])
            ->update();
    }
}
