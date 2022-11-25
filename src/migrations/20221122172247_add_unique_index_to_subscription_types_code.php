<?php
declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddUniqueIndexToSubscriptionTypesCode extends AbstractMigration
{
    public function up()
    {
        $q = <<<SQL
            SELECT code, COUNT(*) 
            FROM subscription_types 
            GROUP BY code HAVING COUNT(*) > 1
SQL;

        if (count($this->fetchAll($q)) > 0) {
            throw new Exception("Unable to add unique index to 'subscription_types' column 'code' since there are duplicate values.");
        }

        $this->table('subscription_types')
            ->removeIndex('code')
            ->addIndex('code', ['unique' => true])
            ->update();
    }

    public function down()
    {
        $this->table('subscription_types')
            ->removeIndex('code')
            ->addIndex('code')
            ->update();
    }
}
