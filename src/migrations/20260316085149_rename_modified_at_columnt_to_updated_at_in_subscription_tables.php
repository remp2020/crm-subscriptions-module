<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class RenameModifiedAtColumntToUpdatedAtInSubscriptionTables extends AbstractMigration
{
    public function change(): void
    {
        $this->table('subscription_types')
            ->renameColumn('modified_at', 'updated_at')
            ->update();

        $this->table('subscriptions')
            ->renameColumn('modified_at', 'updated_at')
            ->update();
    }
}
