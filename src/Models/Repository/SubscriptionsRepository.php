<?php

namespace Crm\SubscriptionsModule\Repository;

use Closure;
use Crm\ApplicationModule\Cache\CacheRepository;
use Crm\ApplicationModule\Hermes\HermesMessage;
use Crm\ApplicationModule\NowTrait;
use Crm\ApplicationModule\Repository;
use Crm\ApplicationModule\Repository\AuditLogRepository;
use Crm\SubscriptionsModule\Events\NewSubscriptionEvent;
use Crm\SubscriptionsModule\Events\SubscriptionEndsEvent;
use Crm\SubscriptionsModule\Events\SubscriptionMovedEvent;
use Crm\SubscriptionsModule\Events\SubscriptionStartsEvent;
use Crm\SubscriptionsModule\Events\SubscriptionUpdatedEvent;
use Crm\SubscriptionsModule\Extension\Extension;
use Crm\SubscriptionsModule\Extension\ExtensionMethodFactory;
use Crm\SubscriptionsModule\Length\LengthMethodFactory;
use DateTime;
use League\Event\Emitter;
use Nette\Database\Explorer;
use Nette\Database\Table\ActiveRow;
use Nette\Database\Table\Selection;

class SubscriptionsRepository extends Repository
{
    use NowTrait;

    const TYPE_REGULAR = 'regular';
    const TYPE_FREE = 'free';
    const TYPE_DONATION = 'donation';
    const TYPE_PREPAID = 'prepaid';

    const INTERNAL_STATUS_UNKNOWN = 'unknown';
    const INTERNAL_STATUS_BEFORE_START = 'before_start';
    const INTERNAL_STATUS_AFTER_END = 'after_end';
    const INTERNAL_STATUS_ACTIVE = 'active';

    protected $tableName = 'subscriptions';

    private $extensionMethodFactory;

    private $lengthMethodFactory;

    private $cacheRepository;

    private $emitter;

    private $hermesEmitter;

    private $types = [
        self::TYPE_REGULAR => self::TYPE_REGULAR,
        self::TYPE_FREE => self::TYPE_FREE,
        self::TYPE_DONATION => self::TYPE_DONATION,
        self::TYPE_PREPAID => self::TYPE_PREPAID
    ];

    public function __construct(
        Explorer $database,
        ExtensionMethodFactory $extensionMethodFactory,
        LengthMethodFactory $lengthMethodFactory,
        AuditLogRepository $auditLogRepository,
        CacheRepository $cacheRepository,
        Emitter $emitter,
        \Tomaj\Hermes\Emitter $hermesEmitter
    ) {
        parent::__construct($database);
        $this->auditLogRepository = $auditLogRepository;
        $this->extensionMethodFactory = $extensionMethodFactory;
        $this->lengthMethodFactory = $lengthMethodFactory;
        $this->cacheRepository = $cacheRepository;
        $this->emitter = $emitter;
        $this->hermesEmitter = $hermesEmitter;
    }

    final public function registerType(string $type)
    {
        $this->types[$type] = $type;
    }

    final public function totalCount($allowCached = false, $forceCacheUpdate = false): int
    {
        $callable = function () {
            return parent::totalCount();
        };
        if ($allowCached) {
            return (int) $this->cacheRepository->loadAndUpdate(
                'subscriptions_count',
                $callable,
                \Nette\Utils\DateTime::from(CacheRepository::REFRESH_TIME_5_MINUTES),
                $forceCacheUpdate
            );
        }
        return $callable();
    }

    final public function add(
        ActiveRow $subscriptionType,
        bool $isRecurrent,
        bool $isPaid,
        ActiveRow $user,
        $type = self::TYPE_REGULAR,
        DateTime $startTime = null,
        DateTime $endTime = null,
        $note = null,
        ActiveRow $address = null,
        bool $sendEmail = true,
        Closure $callbackBeforeNewSubscriptionEvent = null
    ) {
        $isExtending = false;
        // provided $startTime overrides both subscription_types.fixed_start and Extension::getDate()
        if ($startTime === null) {
            if ($subscriptionType->fixed_start) {
                $startTime = $subscriptionType->fixed_start >= $this->getNow() ? $subscriptionType->fixed_start : $this->getNow();
            } else {
                $extension = $this->getSubscriptionExtension($subscriptionType, $user);
                $startTime = $extension->getDate();
                $isExtending = $extension->isExtending();
            }
        }

        $subscriptionLength = $isExtending && $subscriptionType->extending_length ? $subscriptionType->extending_length : $subscriptionType->length;
        if ($endTime === null) {
            $lengthMethod = $this->lengthMethodFactory->getExtension($subscriptionType->length_method_id);
            $length = $lengthMethod->getEndTime($startTime, $subscriptionType, $isExtending);
            $endTime = $length->getEndTime();
            $subscriptionLength = $length->getLength();
        }

        $internalStatus = $this->getInternalStatus($startTime, $endTime);

        /** @var ActiveRow $newSubscription */
        $newSubscription = $this->insert([
            'user_id' => $user->id,
            'subscription_type_id' => $subscriptionType->id,
            'is_recurrent' => $isRecurrent,
            'is_paid' => $isPaid,
            'start_time' => $startTime,
            'end_time' => $endTime,
            'created_at' => new DateTime(),
            'modified_at' => new DateTime(),
            'internal_status' => $internalStatus,
            'type' => $type,
            'length' => $subscriptionLength,
            'note' => $note,
            'address_id' => $address ? $address->id : null,
        ]);

        if ($newSubscription->start_time > new DateTime()) {
            $this->getTable()->where([
                'user_id' => $user->id,
                'end_time' => $newSubscription->start_time
            ])->update(['next_subscription_id' => $newSubscription->id]);
        }

        if ($callbackBeforeNewSubscriptionEvent !== null) {
            $callbackBeforeNewSubscriptionEvent($newSubscription);
        }

        $this->emitter->emit(new NewSubscriptionEvent($newSubscription, $sendEmail));
        $this->hermesEmitter->emit(new HermesMessage('new-subscription', [
            'subscription_id' => $newSubscription->id,
            'send_email' => $sendEmail
        ]));

        $this->emitStatusNotifications($newSubscription);

        return $newSubscription;
    }

    final public function update(ActiveRow &$row, $data)
    {
        $data['modified_at'] = new DateTime();

        // Check if internal status has changed
        $startTime = $row->start_time;
        $endTime = $row->end_time;

        if (isset($data['start_time'])) {
            $startTime = $data['start_time'];
        }

        if (isset($data['end_time'])) {
            $endTime = $data['end_time'];
        }
        $newInternalStatus = $this->getInternalStatus($startTime, $endTime);
        $internalStatusChanged = false;
        if ($newInternalStatus !== $row->internal_status) {
            $data['internal_status'] = $newInternalStatus;
            $internalStatusChanged = true;
        }

        // Check if one of the basic parameters is updated
        $eventTriggeringParams = ['start_time', 'end_time', 'subscription_type_id', 'user_id'];
        $fireUpdateEvent = false;
        foreach ($eventTriggeringParams as $param) {
            if (isset($data[$param]) && $data[$param] != $row->$param) {
                $fireUpdateEvent = true;
                break;
            }
        }

        $result = parent::update($row, $data);
        if ($result) {
            /** @var ActiveRow $row */

            if (isset($data['start_time'])) {
               // remove link from previous subscription if change of start time created gap
                $this->getTable()
                    ->where([
                        'user_id' => $row->user_id,
                        'next_subscription_id' => $row->id,
                        'end_time != ?' => $row->start_time
                    ])
                    ->update(['next_subscription_id' => null]);

                // set new link from previous subscription if change of start time linked them
                $this->getTable()
                    ->where([
                        'user_id' => $row->user_id,
                        'end_time' => $row->start_time,
                    ])
                    ->where('next_subscription_id IS NULL')
                    ->update(['next_subscription_id' => $row->id]);
            }

            if (isset($data['end_time'])) {
                // check if there is next subscription following without gap
                $nextSubscription = $this->getTable()
                    ->where([
                        'user_id' => $row->user_id,
                        'start_time' => $row->end_time,
                    ])
                    ->fetch();

                if (!$nextSubscription && $row->next_subscription_id !== null) {
                    // remove link if it's invalid
                    parent::update($row, ['next_subscription_id' => null]);
                } elseif ($nextSubscription && $nextSubscription->id !== $row->next_subscription_id) {
                    // set new link if found
                    parent::update($row, ['next_subscription_id' => $nextSubscription->id]);
                }
            }

            if ($internalStatusChanged) {
                $this->emitStatusNotifications($row);
            }

            if ($fireUpdateEvent) {
                $this->emitter->emit(new SubscriptionUpdatedEvent($row));
                $this->hermesEmitter->emit(new HermesMessage('update-subscription', [
                    'subscription_id' => $row->id,
                ]));
            }
        }

        return $result;
    }

    private function getInternalStatus(DateTime $startTime, DateTime $endTime): string
    {
        $now = new DateTime();

        if ($startTime <= $now and $endTime > $now) {
            return self::INTERNAL_STATUS_ACTIVE;
        }

        if ($endTime <= $now) {
            return self::INTERNAL_STATUS_AFTER_END;
        }

        if ($startTime > $now) {
            return self::INTERNAL_STATUS_BEFORE_START;
        }

        return self::INTERNAL_STATUS_UNKNOWN;
    }

    // Emits events hooked on 'internal_status' change
    private function emitStatusNotifications(ActiveRow $subscription)
    {
        switch ($subscription->internal_status) {
            case self::INTERNAL_STATUS_ACTIVE:
                $this->emitter->emit(new SubscriptionStartsEvent($subscription));
                break;
            case self::INTERNAL_STATUS_AFTER_END:
                $this->emitter->emit(new SubscriptionEndsEvent($subscription));
                $this->hermesEmitter->emit(new HermesMessage('subscription-ends', [
                    'subscription_id' => $subscription->id,
                ]));
                break;
        }
    }

    final public function refreshInternalStatus(ActiveRow $subscription): bool
    {
        // update with empty parameters will update subscription's internal_status
        return $this->update($subscription, []);
    }

    final public function getSubscriptionExtension($subscriptionType, $user): Extension
    {
        $extensionMethod = $this->extensionMethodFactory->getExtension($subscriptionType->extension_method_id);
        return $extensionMethod->getStartTime($user, $subscriptionType);
    }

    final public function all()
    {
        return $this->getTable();
    }

    final public function availableTypes()
    {
        return $this->types;
    }

    final public function activeSubscriptionTypes()
    {
        return $this->database->table('subscription_type_names')->where(['is_active' => true])->order('sorting');
    }

    final public function hasUserSubscriptionType($userId, $subscriptionTypesCode, DateTime $after = null, int $count = null)
    {
        $subscription_type = $this->database->table('subscription_types')
            ->where('code = ?', $subscriptionTypesCode)->fetch();
        $where = [
            'user_id' => $userId,
            'subscription_type_id' => $subscription_type->id
        ];
        if ($count !== null) {
            if ($count >= $this->getTable()->where($where)->count('*')) {
                return false;
            }
        }
        if ($after) {
            $where['end_time > ?'] = $after;
        }
        return $this->getTable()->where($where)->count('*') > 0;
    }

    /**
     * @param int $userId
     * @return \Nette\Database\Table\Selection
     */
    final public function userSubscriptions($userId): Selection
    {
        return $this->getTable()
            ->where(['subscriptions.user_id' => $userId])
            ->order('subscriptions.end_time DESC, subscriptions.start_time DESC');
    }

    /**
     * @param int $userId
     * @return \Nette\Database\Table\Selection
     */
    final public function userSubscription($userId)
    {
        return $this->getTable()->where(['user_id' => $userId])->limit(1);
    }

    /**
     * @param int $userId
     * @return \Nette\Database\Table\Selection
     */
    final public function userMobileSubscriptions($userId)
    {
        return $this->userSubscriptions($userId)->where(['subscription_type.mobile' => true]);
    }

    /**
     * @param int $userId
     * @return \Nette\Database\Table\ActiveRow
     */
    final public function actualUserSubscription($userId)
    {
        return $this->getTable()->where([
            'user_id' => $userId,
            'start_time <= ?' => $this->getNow(),
            'end_time > ?' => $this->getNow(),
        ])->order('subscription_type.mobile DESC, end_time DESC')->fetch();
    }

    final public function actualUserSubscriptions($userId): Selection
    {
        return $this->getTable()->where([
            'subscriptions.user_id' => $userId,
            'subscriptions.start_time <= ?' => $this->getNow(),
            'subscriptions.end_time > ?' => $this->getNow(),
        ])->order('subscription_type.mobile DESC, end_time DESC');
    }

    final public function actualUserSubscriptionsByContentAccess(DateTime $date, $userId, string ...$contentAccess)
    {
        return $this->actualSubscriptionsByContentAccess($date, ...$contentAccess)
            ->where(['user_id' => $userId]);
    }

    final public function hasSubscriptionEndAfter($userId, DateTime $endTime)
    {
        return $this->getTable()->where(['user_id' => $userId, 'end_time > ?' => $endTime])->count('*') > 0;
    }

    final public function hasPrintSubscriptionEndAfter($userId, DateTime $endTime)
    {
        return $this->getTable()->where([
                    'user_id' => $userId,
                    'end_time > ?' => $endTime,
                ])
                ->where('subscription_type:subscription_type_content_access.content_access.name IN (?)', ['print', 'print_friday'])
                ->count('*') > 0;
    }

    /**
     * @param $date
     * @return \Nette\Database\Table\Selection
     */
    final public function actualSubscriptions(DateTime $date = null)
    {
        if ($date == null) {
            $date = new DateTime();
        }

        return $this->getTable()->where([
            'start_time <= ?' => $date,
            'end_time > ?' => $date,
        ]);
    }

    final public function actualSubscriptionsByContentAccess(DateTime $date, string ...$contentAccess): Selection
    {
        return $this->getTable()->where([
            'subscription_type:subscription_type_content_access.content_access.name' => $contentAccess,
            'start_time <= ?' => $date,
            'end_time > ?' => $date,
        ]);
    }

    final public function latestSubscriptionsByContentAccess(string ...$contentAccess)
    {
        return $this->getTable()->where([
            'subscription_type:subscription_type_content_access.content_access.name' => $contentAccess,
            'end_time > ?' => new DateTime(),
        ])->order('end_time DESC');
    }

    final public function createdOrModifiedSubscriptions(DateTime $fromTime, DateTime $toTime)
    {
        return $this->getTable()->where(
            '(
                (subscriptions.created_at >= ? AND subscriptions.created_at <= ?) OR
                (subscriptions.modified_at >= ? AND subscriptions.modified_at <= ?)
            ) AND subscription_type.print = ?',
            $fromTime,
            $toTime,
            $fromTime,
            $toTime,
            true
        );
    }

    final public function actualClubSubscriptions($date = null)
    {
        // toto by treba zistit ci sa pouziva a kde lebo to nejak dost divne vyzera

        //      if ($date == null) {
//          $date = new DateTime();
//      }
        return $this->getTable()->where('NOT subscription_type.club = ?', 1);
//          ->where('start_time <= ?', $date->format(DateTime::ATOM))
//          ->where('end_time > ?', $date->format(DateTime::ATOM));
    }

    final public function subscriptionsByContentAccess(string ...$contentAccess)
    {
        return $this->getTable()->where([
            'subscription_type:subscription_type_content_access.content_access.name' => $contentAccess,
        ])->group('user_id');
    }

    final public function subscriptionsEndBetween(DateTime $endTimeFrom, DateTime $endTimeTo, $withNextSubscription = null)
    {
        $where = [
            'subscriptions.end_time >=' => $endTimeFrom,
            'subscriptions.end_time <=' => $endTimeTo,
        ];
        if ($withNextSubscription !== null) {
            if ($withNextSubscription === true) {
                $where['subscriptions.next_subscription_id NOT'] = null;
            } else {
                $where['subscriptions.next_subscription_id'] = null;
            }
        }

        return $this->getTable()->where($where)->order('end_time ASC');
    }

    final public function getNewSubscriptionsBetweenDates($from, $to)
    {
        return $this->getTable()->where([
            'subscriptions.start_time >=' => $from,
            'subscriptions.start_time <=' => $to
        ]);
    }

    final public function allSubscribers()
    {
        return $this->getTable()->group('subscriptions.user_id')->order('subscriptions.start_time ASC');
    }

    final public function getExpiredSubscriptions(DateTime $dateTime = null)
    {
        if (!$dateTime) {
            $dateTime = new DateTime();
        }
        return $this->getTable()->select('*')->where([
            'end_time <= ?' => $dateTime,
            'internal_status' => [
                self::INTERNAL_STATUS_ACTIVE,
                self::INTERNAL_STATUS_UNKNOWN
            ]
        ]);
    }

    final public function getStartedSubscriptions(DateTime $dateTime = null)
    {
        if (!$dateTime) {
            $dateTime = new DateTime();
        }
        return $this->getTable()->select('*')->where([
            'start_time <= ?' => $dateTime,
            'end_time > ?' => $dateTime,
            'internal_status' => [
                self::INTERNAL_STATUS_BEFORE_START,
                self::INTERNAL_STATUS_UNKNOWN
            ]
        ]);
    }

    final public function getPreviousSubscription($subscriptionId)
    {
        return $this->getTable()->where([
            'next_subscription_id' => $subscriptionId,
        ])->fetch();
    }

    final public function getCount($subscriptionTypeId, $userId)
    {
        return $this->getTable()
            ->where('subscription_type_id', $subscriptionTypeId)
            ->where('user_id', $userId)
            ->count('*');
    }

    final public function currentSubscribersCount($allowCached = false, $forceCacheUpdate = false)
    {
        $callable = function () {
            return $this->getTable()
                ->select('COUNT(DISTINCT(user.id)) AS total')
                ->where('user.active = ?', true)
                ->where('start_time < ?', $this->database::literal('NOW()'))
                ->where('end_time > ?', $this->database::literal('NOW()'))
                ->fetch()->total;
        };

        if ($allowCached) {
            return $this->cacheRepository->loadAndUpdate(
                'current_subscribers_count',
                $callable,
                \Nette\Utils\DateTime::from('-1 hour'),
                $forceCacheUpdate
            );
        }

        return $callable();
    }

    /**
     * @param DateTime $from
     * @param DateTime $to
     * @return Selection
     */
    final public function subscriptionsCreatedBetween(DateTime $from, DateTime $to)
    {
        return $this->getTable()->where([
            'created_at > ?' => $from,
            'created_at < ?' => $to,
        ]);
    }

    final public function hasAccess($userId, $access, ?DateTime $date = null)
    {
        return $this->getTable()->where([
            'start_time <= ?' => $date ?? new DateTime(),
            'end_time > ?' => $date ?? new DateTime(),
            'user_id' => $userId,
            'subscription_type:subscription_type_content_access.content_access.name' => $access,
        ])->count('*') > 0;
    }

    /**
     * @param DateTime $startTime
     * @param DateTime $endTime
     * @return Selection
     */
    final public function subscriptionsEndingBetween(DateTime $startTime, DateTime $endTime)
    {
        return $this->database->table('subscriptions')
            ->where('subscriptions.id IS NOT NULL')
            ->where('end_time >= ?', $startTime)
            ->where('end_time <= ?', $endTime);
    }

    /**
     * @param DateTime $startTime
     * @param DateTime $endTime
     * @return \Crm\ApplicationModule\Selection
     */
    final public function renewedSubscriptionsEndingBetween(DateTime $startTime, DateTime $endTime)
    {
        return $this->getTable()->where([
            'end_time >= ?' => $startTime,
            'end_time <= ?' => $endTime,
            'next_subscription_id NOT' => null,
        ]);
    }

    final public function userSubscriptionTypesCounts($userId, ?array $subscriptionTypeIds)
    {
        $query = $this->getTable()
            ->select('subscription_type_id, COUNT(*) AS count')
            ->where(['user.id' => $userId])
            ->group('subscription_type_id');

        if ($subscriptionTypeIds !== null) {
            $query->where(['subscription_type_id' => $subscriptionTypeIds]);
        }

        return $query->fetchPairs('subscription_type_id', 'count');
    }

    final public function allWithAddress($addressId)
    {
        return $this->all()->where(['address_id' => $addressId]);
    }

    public function lastActiveUserSubscription(int $userId): Selection
    {
        return $this->getTable()
            ->where([
                'user_id = ?' => $userId,
                'end_time > ?' => $this->getNow(),
                'end_time != start_time', // ignore cancelled subscriptions
            ])
            ->order('end_time DESC')
            ->limit(1);
    }

    public function moveSubscription(ActiveRow $subscription, DateTime $newStartTime)
    {
        /** @var DateTime $originalStartTime */
        $originalStartTime = $subscription->start_time;
        /** @var DateTime $originalEndTime */
        $originalEndTime = $subscription->end_time;

        // We don't use DateTime::diff here, because of inconsistent behavior during leap years.
        // http://sandbox.onlinephpfunctions.com/code/bdcdba7c301d880a3555d36208a030034094df19
        $lengthInSeconds = $subscription->end_time->getTimestamp() - $subscription->start_time->getTimestamp();
        $newEndTime = (clone $newStartTime)->add(new \DateInterval("PT{$lengthInSeconds}S"));

        $this->update($subscription, [
            'start_time' => $newStartTime,
            'end_time' => $newEndTime,
        ]);

        $this->emitter->emit(new SubscriptionMovedEvent($subscription, $originalStartTime, $originalEndTime));
        return $subscription;
    }
}
