<?php

use Phinx\Migration\AbstractMigration;

class ExpandSubscriptionsMetaValueColumn extends AbstractMigration
{
    public function up()
    {
        $this->table('subscriptions_meta')
            ->changeColumn('value', 'string', ['limit' => 2000])
            ->update();
    }

    public function down()
    {
        $this->output->writeln('<error>Data rollback is risky. See migration class for details. Nothing done.</error>');
        // remove return if you are 100% sure you know what you are doing
        return;

        // ensure we have only 255 chars long value
        $this->execute(<<<SQL
            UPDATE `subscriptions_meta`
            SET `value` = SUBSTR(`value`, 1, 255)
            WHERE CHAR_LENGTH(`value`) > 255;
SQL
        );

        // update column size back to VARCHAR(255)
        $this->table('subscriptions_meta')
            ->changeColumn('value', 'string', ['limit' => 255])
            ->update();
    }
}
