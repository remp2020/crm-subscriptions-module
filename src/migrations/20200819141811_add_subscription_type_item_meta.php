<?php

use Phinx\Migration\AbstractMigration;

class AddSubscriptionTypeItemMeta extends AbstractMigration
{
    public function change()
    {
        $this->table('subscription_type_item_meta')
            ->addColumn('subscription_type_item_id', 'integer', ['null' => false])
            ->addColumn('key', 'string', ['null' => false])
            ->addColumn('value', 'string', ['null' => true])
            ->addColumn('created_at', 'datetime', ['null' => false])
            ->addColumn('updated_at', 'datetime', ['null' => false])
            ->addForeignKey('subscription_type_item_id', 'subscription_type_items')
            ->addIndex(['subscription_type_item_id', 'key'], ['unique' => true])
            ->create();
    }
}
