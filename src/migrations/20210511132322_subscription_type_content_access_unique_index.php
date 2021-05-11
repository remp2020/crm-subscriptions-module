<?php

use Phinx\Migration\AbstractMigration;

class SubscriptionTypeContentAccessUniqueIndex extends AbstractMigration
{
    public function change()
    {
        // remove duplicates before adding unique index
        $this->execute(<<<SQL
            DELETE `stca`
            FROM `subscription_type_content_access` AS `stca`
            INNER JOIN `subscription_type_content_access` AS `stca2`
               ON `stca`.`subscription_type_id` = `stca2`.`subscription_type_id`
               AND `stca`.`content_access_id` = `stca2`.`content_access_id`
               AND `stca`.`id` < `stca2`.`id` -- compare only one way to prevent removal of both duplicates
            ;
SQL
        );

        $this->table('subscription_type_content_access')
            ->addIndex(['subscription_type_id', 'content_access_id'], ['unique' => true])
            ->update();
    }
}
