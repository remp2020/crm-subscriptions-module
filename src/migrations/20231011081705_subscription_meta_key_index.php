<?php
declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class SubscriptionMetaKeyIndex extends AbstractMigration
{
    public function up(): void
    {
        $this->table('subscriptions_meta')
            ->addIndex('key')
            ->update();
    }

    public function down(): void
    {
        $this->table('subscriptions_meta')
            ->removeIndex('key')
            ->update();
    }
}
