<?php

namespace Crm\SubscriptionsModule\Components;

use Crm\ApplicationModule\Widget\BaseLazyWidget;
use Crm\ApplicationModule\Widget\LazyWidgetManager;
use Crm\UsersModule\Repository\AddressesRepository;
use Crm\UsersModule\Repository\UserMetaRepository;
use Crm\UsersModule\Repository\UsersRepository;

/**
 * This widget fetches users with print subscription without print address
 * and renders simple bootstrap panel.
 *
 * @package Crm\SubscriptionsModule\Components
 */
class SubscribersWithMissingAddressWidget extends BaseLazyWidget
{
    private $templateName = 'subscribers_with_missing_address_widget.latte';

    protected $usersRepository;

    protected $userMetaRepository;

    protected $addressesRepository;

    protected $contentAccessNames = ['print'];

    protected $addressTypes = ['print'];

    public function __construct(
        LazyWidgetManager $lazyWidgetManager,
        UsersRepository $usersRepository,
        AddressesRepository $addressesRepository,
        UserMetaRepository $userMetaRepository
    ) {
        parent::__construct($lazyWidgetManager);

        $this->usersRepository = $usersRepository;
        $this->addressesRepository = $addressesRepository;
        $this->userMetaRepository = $userMetaRepository;
    }

    public function header($id = '')
    {
        return 'Missing address';
    }

    public function identifier()
    {
        return 'subscribersWithMissingAddressWidget';
    }

    public function setContentAccessNames(string ...$contentAccessNames)
    {
        $this->contentAccessNames = $contentAccessNames;
    }

    public function setAddressTypes(string ...$addressTypes)
    {
        $this->addressTypes = $addressTypes;
    }

    public function render()
    {
        $listUsers = $this->usersRepository->getTable()
            ->where(':subscriptions.start_time <= ?', new \DateTime())
            ->where(':subscriptions.end_time > ?', new \DateTime())
            ->where([
                ':subscriptions.subscription_type:subscription_type_content_access.content_access.name' => $this->contentAccessNames,
            ]);

        $haveAddress = $this->addressesRepository->all()->where(['type' => $this->addressTypes])->select('user_id')->fetchAssoc('user_id=user_id');
        if ($haveAddress) {
            $listUsers->where('users.id NOT IN (?)', $haveAddress);
        }
        $haveDisabledNotification = $this->userMetaRepository->usersWithKey('notify_missing_print_address', 0)->select('user_id')->fetchAssoc('user_id=user_id');
        if ($haveDisabledNotification) {
            $listUsers->where('users.id NOT IN (?)', $haveDisabledNotification);
        }

        if (!empty($listUsers)) {
            $this->template->listUsers = $listUsers;

            $this->template->setFile(__DIR__ . '/' . $this->templateName);
            $this->template->render();
        }
    }
}
