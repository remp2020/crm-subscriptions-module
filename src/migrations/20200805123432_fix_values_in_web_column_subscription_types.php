<?php

use Phinx\Migration\AbstractMigration;

class FixValuesInWebColumnSubscriptionTypes extends AbstractMigration
{
    public function up()
    {
        $sql = <<<SQL
            UPDATE subscription_types SET web = 0;
        
            UPDATE subscription_types SET web = 1 
            WHERE id in (SELECT subscription_type_id
                            FROM subscription_type_content_access
                            WHERE content_access_id = (SELECT id from content_access WHERE name = 'web'));
SQL;

        $this->execute($sql);
    }

    public function down()
    {
        // TODO: [refactoring] add down migrations for module init migrations
        $this->output->writeln('Down migration is not available.');
    }

}
