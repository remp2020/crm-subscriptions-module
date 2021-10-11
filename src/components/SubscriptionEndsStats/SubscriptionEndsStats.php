<?php

namespace Crm\SubscriptionsModule\Components;

use Crm\SubscriptionsModule\Repository\SubscriptionTypesRepository;
use Crm\SubscriptionsModule\Repository\SubscriptionsRepository;
use Nette\Application\UI;
use Nette\Localization\ITranslator;
use Nette\Utils\DateTime;

/**
 * This component fetches ending subscriptions and renders 2 table.
 * First with count for each subscription type and second with subscriptions content access.
 *
 * @package Crm\SubscriptionsModule\Components
 */
class SubscriptionEndsStats extends UI\Control
{
    private $templateName = 'subscription_end_stats.latte';

    /** @var SubscriptionsRepository */
    private $subscriptionsRepository;

    /** @var SubscriptionTypesRepository  */
    private $subscriptionTypesRepository;

    private $translator;

    private $startTime;

    private $endTime;

    private $withoutNext;

    private $withoutRecurrent;

    private $freeSubscriptions = true;

    public function __construct(
        SubscriptionsRepository $subscriptionsRepository,
        SubscriptionTypesRepository $subscriptionTypesRepository,
        ITranslator $translator
    ) {
        parent::__construct();
        $this->subscriptionsRepository = $subscriptionsRepository;
        $this->subscriptionTypesRepository = $subscriptionTypesRepository;
        $this->translator = $translator;
    }

    public function render()
    {
        $subscriptions = $this->subscriptionsRepository->subscriptionsEndBetween($this->startTime, $this->endTime, $this->withoutNext ? false : null);
        $subscriptions1 = $this->subscriptionsRepository->subscriptionsEndBetween($this->startTime, $this->endTime, false);

        if (!$this->freeSubscriptions) {
            $subscriptions
                ->where('subscription_type.price > ?', 0)
                ->where('subscriptions.type NOT IN (?)', ['free']);
        }

        if ($this->withoutRecurrent) {
            $subscriptions->where('subscriptions.id NOT', $subscriptions1->where([
                ':payments:recurrent_payments.status' => null,
                ':payments:recurrent_payments.retries > ?' => 0,
                ':payments:recurrent_payments.state = ?' => 'active'
            ])->fetchPairs(null, 'id'));
        }

        $data = $subscriptions->fetchAll();

        list($typesCounts, $contents) = $this->getCounts($data);
        $this->template->typesCounts = $typesCounts;
        $this->template->contents = $contents;

        $this->template->setTranslator($this->translator);

        $this->template->setFile(__DIR__ . '/' . $this->templateName);
        $this->template->render();
    }

    public function setStartTime(DateTime $startTime)
    {
        $this->startTime = $startTime;
    }

    public function setEndTime(DateTime $endTime)
    {
        $this->endTime = $endTime;
    }

    public function setWithoutNext($withoutNext)
    {
        $this->withoutNext = $withoutNext;
    }

    public function setWithoutRecurrent($withoutRecurrent)
    {
        $this->withoutRecurrent = $withoutRecurrent;
    }

    public function setFreeSubscriptions($freeSubscriptions)
    {
        $this->freeSubscriptions = $freeSubscriptions;
    }

    private function getCounts($data)
    {
        $subscriptionsTypes = $this->subscriptionTypesRepository->all()->fetchAll();

        $types = [];
        $contents = [
            'web' => 0,
            'print' => 0,
            'club' => 0,
            'mobile' => 0,
        ];
        $totalContents = 0;
        foreach ($data as $subscription) {
            if (!isset($types[$subscription->subscription_type_id])) {
                $types[$subscription->subscription_type_id] = 0;
            }

            $types[$subscription->subscription_type_id] = $types[$subscription->subscription_type_id] + 1;

            $subscriptionType = $subscriptionsTypes[$subscription->subscription_type_id];
            foreach ($contents as $key => $value) {
                if ($subscriptionType->$key) {
                    $contents[$key]++;
                    $totalContents++;
                }
            }
        }

        $resultContents = [];
        foreach ($contents as $key => $value) {
            $resultContents[$key] = [
                'count' => $value,
                'per' => $totalContents > 0 ? round($value / $totalContents * 100, 2) : 0,
            ];
        }

        $types = $this->processTypes($subscriptionsTypes, $types);
        
        usort($types, function ($a, $b) {
            return $a['count'] < $b['count'];
        });

        return [$types, $resultContents];
    }

    private function processTypes($subscriptionTypes, $typesCounts)
    {
        $total = array_sum($typesCounts);
        if ($total == 0) {
            return [];
        }
        $result = [];
        foreach ($typesCounts as $id => $count) {
            $result[] = [
                'type' => $subscriptionTypes[$id],
                'count' => $count,
                'per' => round($count / $total * 100, 2),
            ];
        }
        return $result;
    }
}
