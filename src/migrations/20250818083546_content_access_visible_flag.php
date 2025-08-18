<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class ContentAccessVisibleFlag extends AbstractMigration
{
    public function up(): void
    {
        $this->table('content_access')
            // all current content accesses should be visible by default
            ->addColumn('is_visible', 'boolean', ['default' => true])
            ->update();

        $this->table('content_access')
            // make it not nullable and without default; the source of truth for default is the repository class
            ->changeColumn('is_visible', 'boolean', ['null' => false])
            ->update();
    }

    public function down(): void
    {
        $this->table('content_access')
            // all current content accesses should be visible by default
            ->removeColumn('is_visible')
            ->update();
    }
}
