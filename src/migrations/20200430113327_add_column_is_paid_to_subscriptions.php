<?php

use Phinx\Migration\AbstractMigration;

class AddColumnIsPaidToSubscriptions extends AbstractMigration
{
    public function change()
    {
        $table = $this->table('subscriptions');

        if (!$table->hasColumn('is_paid')) {
            $table->addColumn('is_paid', 'boolean', ['null' => true, 'after' => 'is_recurrent'])
                ->update();
        }

        $this->query("UPDATE subscriptions s SET s.is_paid = 0 WHERE s.type IN ('bloger', 'donation', 'free')");
        $this->query("UPDATE subscriptions s SET s.is_paid = 1 WHERE s.type IN ('prepaid', 'upgrade', 'gift')");
    }
}
