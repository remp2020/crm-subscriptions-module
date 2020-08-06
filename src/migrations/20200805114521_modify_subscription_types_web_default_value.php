<?php

use Phinx\Migration\AbstractMigration;

class ModifySubscriptionTypesWebDefaultValue extends AbstractMigration
{
    public function up()
    {
        $subscriptionTypes = $this->table('subscription_types');
        $subscriptionTypes->changeColumn('web', 'boolean', [
            'default' => 0
        ])->update();
    }

    public function down()
    {
        $subscriptionTypes = $this->table('subscription_types');
        $subscriptionTypes->changeColumn('web', 'boolean', [
            'default' => 1
        ])->update();
    }
}
