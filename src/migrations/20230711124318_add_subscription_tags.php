<?php
declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddSubscriptionTags extends AbstractMigration
{
    /**
     * Change Method.
     *
     * Write your reversible migrations using this method.
     *
     * More information on writing migrations is available here:
     * https://book.cakephp.org/phinx/0/en/migrations.html#the-change-method
     *
     * Remember to call "create()" or "update()" and NOT "save()" when working
     * with the Table class.
     */
    public function change(): void
    {
        $table = $this->table('subscription_type_tags');
        $table
            ->addColumn('subscription_type_id', 'integer', ['null' => false])
            ->addColumn('tag', 'string', ['null' => false])
            ->addIndex(['subscription_type_id', 'tag'], ['unique' => true])
            ->addForeignKey('subscription_type_id', 'subscription_types', 'id')
            ->create();
    }
}
