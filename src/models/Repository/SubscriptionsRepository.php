<?php

namespace Crm\SubscriptionsModule\Repository;

use Closure;
use Crm\ApplicationModule\Cache\CacheRepository;
use Crm\ApplicationModule\Hermes\HermesMessage;
use Crm\ApplicationModule\Repository;
use Crm\ApplicationModule\Repository\AuditLogRepository;
use Crm\SubscriptionsModule\Events\NewSubscriptionEvent;
use Crm\SubscriptionsModule\Events\SubscriptionEndsEvent;
use Crm\SubscriptionsModule\Events\SubscriptionStartsEvent;
use Crm\SubscriptionsModule\Extension\Extension;
use Crm\SubscriptionsModule\Extension\ExtensionMethodFactory;
use Crm\SubscriptionsModule\Length\LengthMethodFactory;
use DateTime;
use League\Event\Emitter;
use Nette\Database\Context;
use Nette\Database\Table\ActiveRow;
use Nette\Database\Table\IRow;
use Nette\Database\Table\Selection;

class SubscriptionsRepository extends Repository
{
    const TYPE_REGULAR = 'regular';
    const TYPE_FREE = 'free';
    const TYPE_DONATION = 'donation';
    const TYPE_GIFT = 'gift';
    const TYPE_SPECIAL = 'special';
    const TYPE_UPGRADE = 'upgrade';
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

    public function __construct(
        Context $database,
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

    final public function totalCount($allowCached = false, $forceCacheUpdate = false)
    {
        $callable = function () {
            return parent::totalCount();
        };
        if ($allowCached) {
            return $this->cacheRepository->loadAndUpdate(
                'subscriptions_count',
                $callable,
                \Nette\Utils\DateTime::from(CacheRepository::REFRESH_TIME_5_MINUTES),
                $forceCacheUpdate
            );
        }
        return $callable();
    }

    final public function add(
        IRow $subscriptionType,
        bool $isRecurrent,
        bool $isPaid,
        IRow $user,
        $type = self::TYPE_REGULAR,
        DateTime $startTime = null,
        DateTime $endTime = null,
        $note = null,
        IRow $address = null,
        bool $sendEmail = true,
        Closure $callbackBeforeNewSubscriptionEvent = null
    ) {
        $isExtending = false;
        if ($startTime == null) {
            $extension = $this->getSubscriptionExtension($subscriptionType, $user);
            $startTime = $extension->getDate();
            $isExtending = $extension->isExtending();
        }

        if (!$isExtending && $subscriptionType->fixed_start) {
            $startTime = $subscriptionType->fixed_start;
        }

        $subscriptionLength = $isExtending && $subscriptionType->extending_length ? $subscriptionType->extending_length : $subscriptionType->length;
        if ($endTime == null) {
            $lengthMethod = $this->lengthMethodFactory->getExtension($subscriptionType->length_method_id);
            $length = $lengthMethod->getEndTime($startTime, $subscriptionType, $isExtending);
            $endTime = $length->getEndTime();
            $subscriptionLength = $length->getLength();
        }

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
            'internal_status' => self::INTERNAL_STATUS_UNKNOWN,
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

        return $newSubscription;
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
        return [
            self::TYPE_REGULAR => self::TYPE_REGULAR,
            self::TYPE_FREE => self::TYPE_FREE,
            self::TYPE_DONATION => self::TYPE_DONATION,
            self::TYPE_GIFT => self::TYPE_GIFT,
            self::TYPE_SPECIAL => self::TYPE_SPECIAL,
            self::TYPE_UPGRADE => self::TYPE_UPGRADE,
            self::TYPE_PREPAID => self::TYPE_PREPAID,
        ];
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
            'start_time <= ?' => new DateTime,
            'end_time > ?' => new DateTime,
        ])->order('subscription_type.mobile DESC, end_time DESC')->fetch();
    }

    final public function actualUserSubscriptions($userId): Selection
    {
        return $this->getTable()->where([
            'subscriptions.user_id' => $userId,
            'subscriptions.start_time <= ?' => new DateTime,
            'subscriptions.end_time > ?' => new DateTime,
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

    final public function update(IRow &$row, $data)
    {
        $values['modified_at'] = new DateTime();
        $result = parent::update($row, $data);
        if ($result) {
            /** @var ActiveRow $row */
            $this->getTable()
                ->where([
                    'user_id' => $row->user_id,
                    'end_time' => $row->start_time
                ])
                ->where('next_subscription_id IS NULL')
                ->update(['next_subscription_id' => $row->id]);
        }
        return $result;
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

    final public function setExpired($subscription, $endTime = null, string $note = null)
    {
        $data = [
            'internal_status' => SubscriptionsRepository::INTERNAL_STATUS_AFTER_END,
            'modified_at' => new DateTime(),
        ];
        if ($note) {
            $data['note'] = $note;
        }
        if ($endTime) {
            $data['end_time'] = $endTime;
        }
        $this->update($subscription, $data);
        $this->emitter->emit(new SubscriptionEndsEvent($subscription));
        $this->hermesEmitter->emit(new HermesMessage('subscription-ends', [
            'subscription_id' => $subscription->id,
        ]));
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

    final public function setStarted($subscription)
    {
        $this->update($subscription, ['internal_status' => SubscriptionsRepository::INTERNAL_STATUS_ACTIVE]);
        $this->emitter->emit(new SubscriptionStartsEvent($subscription));
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

    public function lastActiveUserSubscription($userId): Selection
    {
        return $this->getTable()
            ->where([
                'user_id = ?' => $userId,
                'end_time > ?' => new DateTime()
            ])
            ->order('end_time DESC')
            ->limit(1);
    }
}
