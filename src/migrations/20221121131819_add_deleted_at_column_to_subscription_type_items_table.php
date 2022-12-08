<?php
declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddDeletedAtColumnToSubscriptionTypeItemsTable extends AbstractMigration
{
    public function change()
    {
        $this->table('subscription_type_items')
            ->addColumn('deleted_at', 'datetime', ['null' => true])
            ->update();
    }
}
