<?php

use Phinx\Migration\AbstractMigration;

// Basically reverts `ExpandSubscriptionsMetaValueColumn`
class ShortenSubscriptionsMetaValueColumn extends AbstractMigration
{
    public function up()
    {
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

    public function down()
    {
        $this->table('subscriptions_meta')
            ->changeColumn('value', 'string', ['limit' => 2000])
            ->update();
    }
}
