<?php

namespace Crm\SubscriptionsModule\Builder;

use Crm\ApplicationModule\Builder\Builder;
use Crm\ApplicationModule\Config\ApplicationConfig;
use Crm\SubscriptionsModule\Repository\ContentAccessRepository;
use Crm\SubscriptionsModule\Repository\SubscriptionTypeItemMetaRepository;
use Crm\SubscriptionsModule\Repository\SubscriptionTypesRepository;
use Nette\Database\Context;
use Nette\Utils\DateTime;

class SubscriptionTypeBuilder extends Builder
{
    private $applicationConfig;

    protected $tableName = 'subscription_types';

    protected $metaTableName = 'subscription_types_meta';

    private $subscriptionTypeItemsTable = 'subscription_type_items';

    private $contentAccessRepository;

    private $subscriptionTypesRepository;

    private $subscriptionTypeItemMetaRepository;

    private $metaItems = [];

    private $subscriptionTypeItems = [];

    public function __construct(
        Context $database,
        ApplicationConfig $applicationConfig,
        ContentAccessRepository $contentAccessRepository,
        SubscriptionTypeItemMetaRepository $subscriptionTypeItemMetaRepository,
        SubscriptionTypesRepository $subscriptionTypesRepository
    ) {
        parent::__construct($database);
        $this->applicationConfig = $applicationConfig;
        $this->contentAccessRepository = $contentAccessRepository;
        $this->subscriptionTypesRepository = $subscriptionTypesRepository;
        $this->subscriptionTypeItemMetaRepository = $subscriptionTypeItemMetaRepository;
    }

    public function isValid()
    {
        if (strlen($this->get('name')) < 1) {
            $this->addError('Nebolo zadané meno');
            return false;
        }
        if (!$this->exists('price')) {
            $this->addError('Nebola zadaná cena');
        }

        if (count($this->getErrors()) > 0) {
            return false;
        }
        return true;
    }

    /**
     * @return SubscriptionTypeBuilder
     */
    public function createNew()
    {
        $this->subscriptionTypeItems = [];
        $this->metaItems = [];
        return parent::createNew();
    }

    protected function setDefaults()
    {
        parent::setDefaults();
        $this->set('created_at', new \DateTime());
        $this->set('modified_at', new \DateTime());
        $this->set('disable_notifications', false);
        $this->set('length_method_id', 'fix_days');
    }

    public function setName($name)
    {
        return $this->set('name', $name);
    }

    public function setUserLabel($userLabel)
    {
        return $this->set('user_label', $userLabel);
    }

    /**
     * Use this setter if you want to save same string as `name` and `user_label`.
     *
     * @param $nameAndUserLabel
     * @return SubscriptionTypeBuilder
     */
    public function setNameAndUserLabel($nameAndUserLabel)
    {
        return $this->setName($nameAndUserLabel)->setUserLabel($nameAndUserLabel);
    }

    public function setCode($code)
    {
        return $this->set('code', $code);
    }

    public function setDescription($description)
    {
        return $this->set('description', $description);
    }

    public function setPrice($price)
    {
        return $this->set('price', $price);
    }

    public function setLength($length)
    {
        return $this->set('length', $length);
    }

    public function setFixedEnd($fixedEnd)
    {
        if ($fixedEnd) {
            return $this->set('fixed_end', $fixedEnd);
        }
        return $this;
    }

    public function setFixedStart($fixedStart)
    {
        if ($fixedStart) {
            return $this->set('fixed_start', $fixedStart);
        }
        return $this;
    }

    public function setExtendingLength($extendingLength)
    {
        return $this->set('extending_length', $extendingLength);
    }

    public function setActive($active)
    {
        return $this->set('active', $active);
    }

    public function setVisible($visible)
    {
        return $this->set('visible', $visible);
    }

    public function setContentAccessOption(...$contentAccess)
    {
        return $this->setOption('content_access', array_fill_keys($contentAccess, 1));
    }

    public function setDefault($default)
    {
        return $this->set('default', $default);
    }

    public function setSorting($sorting)
    {
        return $this->set('sorting', $sorting);
    }

    public function setNoSubscription($boolean)
    {
        return $this->set('no_subscription', $boolean);
    }

    public function setNextSubscriptionTypeId($id)
    {
        return $this->set('next_subscription_type_id', $id);
    }

    public function setNextSubscriptionTypeIdFromCode(string $code)
    {
        $subscriptionType = $this->subscriptionTypesRepository->findByCode($code);
        if (!$subscriptionType) {
            throw new \Exception("Provided subscription type with ID [{$code}] doesn't exist.");
        }
        return $this->setNextSubscriptionTypeId($subscriptionType->id);
    }

    public function setLimitPerUser($limitPerUser)
    {
        return $this->set('limit_per_user', $limitPerUser);
    }

    public function setAskAddress($askAddress)
    {
        return $this->set('ask_address', $askAddress);
    }

    public function setExtensionMethod($extensionMethod)
    {
        return $this->set('extension_method_id', $extensionMethod);
    }

    public function setLengthMethod($lengthMethod)
    {
        return $this->set('length_method_id', $lengthMethod);
    }

    public function setDisabledNotifications($disableNotifications)
    {
        return $this->set('disable_notifications', $disableNotifications);
    }

    public function setRecurrentChargeBefore($recurrentChargeBefore)
    {
        return $this->set('recurrent_charge_before', $recurrentChargeBefore);
    }

    public function addSubscriptionTypeItem($name, $amount, $vat, $meta = [])
    {
        $this->subscriptionTypeItems[] = [
            'name' => $name,
            'amount' => $amount,
            'vat' => $vat,
            'meta' => $meta
        ];
        return $this;
    }

    public function addMeta($key, $value)
    {
        $this->metaItems[$key] = $value;
        return $this;
    }

    public function setContentAccess($contentAccess)
    {
        foreach ($contentAccess as $key => $value) {
            $this->set($key, $value);
        }
        return $this;
    }

    public function processContentTypes($subscriptionType, array $values)
    {
        $values = array_filter($values);
        $access = $this->contentAccessRepository->all()->fetchPairs('name', 'name');

        foreach ($access as $key) {
            if (isset($values[$key])) {
                if (!$this->contentAccessRepository->hasAccess($subscriptionType, $key)) {
                    $this->contentAccessRepository->addAccess($subscriptionType, $key);
                }
            } else {
                $this->contentAccessRepository->removeAccess($subscriptionType, $key);
            }
        }
    }

    protected function store($tableName)
    {
        $contentAccess = $this->getOption('content_access') ?? [];

        // content access legacy
        foreach ($contentAccess as $key => $_) {
            if (in_array($key, ['web', 'print', 'club', 'mobile', 'print_friday', 'ad_free'])) {
                $this->set($key, 1);
            }
        }

        $subscriptionType = parent::store($tableName);
        $this->processContentTypes($subscriptionType, $contentAccess);

        if (count($this->subscriptionTypeItems)) {
            $sorting = 100;
            foreach ($this->subscriptionTypeItems as $item) {
                $subscriptionTypeItem = $this->database->table($this->subscriptionTypeItemsTable)->insert([
                    'subscription_type_id' => $subscriptionType->id,
                    'name' => $item['name'],
                    'amount' => $item['amount'],
                    'vat' => $item['vat'],
                    'sorting' => $sorting,
                    'created_at' => new DateTime(),
                    'updated_at' => new DateTime(),
                ]);
                foreach ($item['meta'] as $key => $value) {
                    $this->subscriptionTypeItemMetaRepository->add($subscriptionTypeItem, $key, $value);
                }
                $sorting += 100;
            }
        } else {
            $this->database->table($this->subscriptionTypeItemsTable)->insert([
                'subscription_type_id' => $subscriptionType->id,
                'name' => $this->get('user_label') ? $this->get('user_label') : $this->get('name'),
                'amount' => $this->get('price'),
                'vat' => $this->applicationConfig->get('vat_default') ? $this->applicationConfig->get('vat_default') : 0,
                'sorting' => 100,
                'created_at' => new DateTime(),
                'updated_at' => new DateTime(),
            ]);
        }

        foreach ($this->metaItems as $key => $value) {
            $this->database->table($this->metaTableName)->insert([
                'subscription_type_id' => $subscriptionType->id,
                'key' => $key,
                'value' => $value,
                'sorting' => 100,
                'created_at' => new DateTime(),
                'updated_at' => new DateTime(),
            ]);
        }

        return $subscriptionType;
    }
}
