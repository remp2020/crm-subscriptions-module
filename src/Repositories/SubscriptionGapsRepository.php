<?php

namespace Crm\SubscriptionsModule\Repositories;

use Crm\ApplicationModule\Models\Database\Repository;
use Nette\Database\Explorer;
use Nette\Database\Table\ActiveRow;
use Nette\Utils\DateTime;

class SubscriptionGapsRepository extends Repository
{
    protected $tableName = 'subscription_gaps';

    private const int MIN_GAP_DAYS = 28;

    public function __construct(Explorer $database, private readonly SubscriptionsRepository $subscriptionsRepository)
    {
        parent::__construct($database);
    }

    final public function add(int $userId, DateTime $gapStart, DateTime $gapEnd, int $gapLengthDays): ActiveRow
    {
        return $this->insert([
            'user_id' => $userId,
            'gap_start' => $gapStart,
            'gap_end' => $gapEnd,
            'gap_length_days' => $gapLengthDays,
            'created_at' => new DateTime(),
        ]);
    }

    final public function addMultiple(array $rows): void
    {
        if (empty($rows)) {
            return;
        }
        $this->getTable()->insert($rows);
    }

    final public function getForUser(int $userId): ?ActiveRow
    {
        return $this->getTable()->where('user_id', $userId)->fetch() ?: null;
    }

    final public function hasReactivatingGap(int $userId, \DateTimeInterface $subscriptionStart, int $minGapDays, int $maxGapDays): bool
    {
        $windowStart = DateTime::from($subscriptionStart)->modify("-{$maxGapDays} days");
        return (bool) $this->getTable()
            ->where('user_id', $userId)
            ->where('gap_length_days > ?', $minGapDays)
            ->where('gap_end >= ?', $windowStart)
            ->where('gap_end <= ?', $subscriptionStart)
            ->fetch();
    }

    final public function deleteForUsers(array $userIds): void
    {
        if (empty($userIds)) {
            return;
        }
        $this->getTable()->where('user_id', $userIds)->delete();
    }

    final public function deleteAll(): void
    {
        $this->getTable()->delete();
    }

    final public function recalculateForUsers(array $userIds, bool $skipDelete = false): int
    {
        if (empty($userIds)) {
            return 0;
        }

        $sortedSubscriptions = $this->subscriptionsRepository->all()
            ->where('user_id', $userIds)
            ->where('start_time <= ?', new DateTime())
            ->select('user_id, start_time, end_time')
            ->order('user_id ASC, start_time ASC')
            ->fetchAll();

        $intervalsByUser = [];
        foreach ($sortedSubscriptions as $sub) {
            $intervalsByUser[$sub->user_id][] = [$sub->start_time, $sub->end_time];
        }

        $now = new DateTime();
        $insertRows = [];
        $usersWithGaps = 0;

        foreach ($userIds as $userId) {
            $intervals = $intervalsByUser[$userId] ?? [];
            if (empty($intervals)) {
                continue;
            }
            $merged = $this->mergeIntervals($intervals);
            $gaps = $this->findGaps($merged);

            if (empty($gaps)) {
                continue;
            }
            $usersWithGaps++;
            foreach ($gaps as [$gapStart, $gapEnd, $gapDays]) {
                $insertRows[] = [
                    'user_id' => $userId,
                    'gap_start' => $gapStart,
                    'gap_end' => $gapEnd,
                    'gap_length_days' => $gapDays,
                    'created_at' => $now,
                ];
            }
        }

        if (!$skipDelete) {
            $this->deleteForUsers($userIds);
        }
        $this->addMultiple($insertRows);

        return $usersWithGaps;
    }

    /**
     * Merges overlapping or adjacent subscription intervals into continuous coverage blocks.
     * Input must be sorted by start date ascending. Each interval is a [DateTime $start, DateTime $end] tuple.
     * Needed so concurrent subscriptions don't produce false gaps.
     */
    private function mergeIntervals(array $intervals): array
    {
        if (count($intervals) <= 1) {
            return $intervals;
        }

        $merged = [$intervals[0]];
        for ($i = 1; $i < count($intervals); $i++) {
            $lastIdx = count($merged) - 1;
            if ($intervals[$i][0] <= $merged[$lastIdx][1]) {
                if ($intervals[$i][1] > $merged[$lastIdx][1]) {
                    $merged[$lastIdx][1] = $intervals[$i][1];
                }
            } else {
                $merged[] = $intervals[$i];
            }
        }

        return $merged;
    }

    /**
     * Returns gaps between consecutive merged intervals exceeding MIN_GAP_DAYS.
     * Each gap is a [gap_start, gap_end, gap_length_days] tuple where gap_start is the end of
     * the preceding interval and gap_end is the start of the following interval.
     */
    private function findGaps(array $intervals): array
    {
        $gaps = [];
        for ($i = 1; $i < count($intervals); $i++) {
            $gapDays = (int)(($intervals[$i][0]->getTimestamp() - $intervals[$i - 1][1]->getTimestamp()) / 86400);
            if ($gapDays > self::MIN_GAP_DAYS) {
                $gaps[] = [$intervals[$i - 1][1], $intervals[$i][0], $gapDays];
            }
        }
        return $gaps;
    }
}
