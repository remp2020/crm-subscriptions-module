<?php

namespace Crm\SubscriptionsModule\Repositories;

use Crm\ApplicationModule\Models\Database\Repository;
use Crm\ApplicationModule\Models\Database\Selection;
use Nette\Database\Table\ActiveRow;

class SubscriptionTypeTagsRepository extends Repository
{
    protected $tableName = 'subscription_type_tags';

    final public function add(ActiveRow $subscriptionType, string $tagName): void
    {
        $this->insert([
            'subscription_type_id' => $subscriptionType->id,
            'tag' => $tagName
        ]);
    }

    /**
     * @return string[]
     */
    final public function tagsSortedByOccurrences(): array
    {
        return $this->getTable()
            ->select("tag")
            ->group('tag')
            ->order('COUNT(*) DESC')
            ->order('tag ASC')
            ->fetchPairs('tag', 'tag');
    }

    final public function all(): Selection
    {
        return $this->getTable();
    }

    final public function removeTagsForSubscriptionType(ActiveRow $subscriptionType): void
    {
            $this->getTable()->where(['subscription_type_id' => $subscriptionType->id])->delete();
    }

    final public function setTagsForSubscriptionType(ActiveRow $subscriptionType, array $tags): void
    {
        $this->database->transaction(function () use ($tags, $subscriptionType) {
            $this->removeTagsForSubscriptionType($subscriptionType);
            foreach ($tags as $tag) {
                $this->insert([
                    'subscription_type_id' => $subscriptionType->id,
                    'tag' => $tag
                ]);
            }
        });
    }
}
