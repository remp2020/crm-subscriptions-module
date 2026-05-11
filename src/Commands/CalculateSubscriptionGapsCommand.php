<?php

namespace Crm\SubscriptionsModule\Commands;

use Crm\ApplicationModule\Commands\DecoratedCommandTrait;
use Crm\SubscriptionsModule\Repositories\SubscriptionGapsRepository;
use Crm\SubscriptionsModule\Repositories\SubscriptionsRepository;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Tracy\Debugger;

class CalculateSubscriptionGapsCommand extends Command
{
    use DecoratedCommandTrait;

    private const int BATCH_SIZE = 1000;

    public function __construct(
        private readonly SubscriptionsRepository $subscriptionsRepository,
        private readonly SubscriptionGapsRepository $subscriptionGapsRepository,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('subscriptions:calculate_gaps')
            ->setDescription('Calculate subscription gaps per user across full subscription history')
            ->addOption('from', null, InputOption::VALUE_REQUIRED, 'Only recompute gaps for users with subscriptions starting on or after this date (Y-m-d). Omit for full rebuild.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        Debugger::timer('calculate_gaps');

        $fromDate = $input->getOption('from');
        if ($fromDate !== null) {
            try {
                $from = new \DateTime($fromDate);
                $from->setTime(0, 0, 0);
            } catch (\Exception) {
                $output->writeln("<error>Invalid --from value: {$fromDate}</error>");
                return Command::FAILURE;
            }
        }

        $countQuery = $this->subscriptionsRepository->all()
            ->select('COUNT(DISTINCT user_id)')
            ->where('start_time <= ?', new \DateTime());
        if (isset($from)) {
            $countQuery = $countQuery->where('start_time >= ?', $from);
        }
        $totalUsers = (int) $countQuery->fetchField();

        if (!isset($from)) {
            $this->subscriptionGapsRepository->deleteAll();
        }

        $progressBar = new ProgressBar($output, $totalUsers);
        $progressBar->setFormat(' %current%/%max% [%bar%] %percent:3s%% %elapsed:6s%/%estimated:-6s%');
        $progressBar->start();

        $processedCount = 0;
        $usersWithGaps = 0;
        $lastUserId = 0;
        $skipDelete = !isset($from);

        do {
            $userIds = $this->subscriptionsRepository->all()
                ->select('DISTINCT user_id')
                ->where('user_id > ?', $lastUserId)
                ->where('start_time <= ?', new \DateTime())
                ->order('user_id ASC')
                ->limit(self::BATCH_SIZE);

            if (isset($from)) {
                $userIds = $userIds->where('start_time >= ?', $from);
            }

            $userIds = array_values($userIds->fetchPairs('user_id', 'user_id'));

            if (!empty($userIds)) {
                $usersWithGaps += $this->subscriptionGapsRepository->recalculateForUsers($userIds, $skipDelete);
                $processedCount += count($userIds);
                $progressBar->advance(count($userIds));
                $lastUserId = (int) end($userIds);
            }
        } while (count($userIds) === self::BATCH_SIZE);

        $progressBar->finish();
        $output->writeln('');

        $elapsed = round(Debugger::timer('calculate_gaps'), 2);
        $mode = isset($from) ? "from {$fromDate}" : 'full rebuild';
        $this->line("Processed {$processedCount} users ({$mode}), {$usersWithGaps} users have gaps. Time: {$elapsed}s");

        return Command::SUCCESS;
    }
}
