<?php declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class RenameActiveSubscriptionSegmentKeys extends AbstractMigration
{
    public function up(): void
    {
        $this->execute("UPDATE segments SET criteria = REPLACE(criteria, '\"key\":\"users_active_subscription\"', '\"key\":\"users_with_subscription\"')");
        $this->execute("UPDATE segments SET criteria = REPLACE(criteria, '\"key\":\"subscriptions_active_subscription\"', '\"key\":\"subscriptions_with_subscription\"')");
    }

    public function down(): void
    {
        $this->execute("UPDATE segments SET criteria = REPLACE(criteria, '\"key\":\"users_with_subscription\"', '\"key\":\"users_active_subscription\"')");
        $this->execute("UPDATE segments SET criteria = REPLACE(criteria, '\"key\":\"subscriptions_with_subscription\"', '\"key\":\"subscriptions_active_subscription\"')");
    }
}
