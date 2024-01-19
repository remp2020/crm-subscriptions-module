<?php

namespace Crm\SubscriptionsModule\Components\PrintSubscribersWithoutPrintAddressWidget;

use Crm\ApplicationModule\Models\Widget\BaseLazyWidget;
use Crm\ApplicationModule\Models\Widget\LazyWidgetManager;
use Crm\SubscriptionsModule\Repositories\SubscriptionsRepository;
use Crm\UsersModule\Repositories\AddressesRepository;
use Crm\UsersModule\Repositories\UserMetaRepository;

/**
 * This widget fetches users with print subscription without print address
 * and renders simple bootstrap panel.
 *
 * @package Crm\SubscriptionsModule\Components
 */
class PrintSubscribersWithoutPrintAddressWidget extends BaseLazyWidget
{
    private string $templateName = 'print_subscribers_without_print_address_widget.latte';

    protected array $contentAccessNames = ['print'];

    protected array $addressTypes = ['print'];

    public function __construct(
        LazyWidgetManager $lazyWidgetManager,
        private AddressesRepository $addressesRepository,
        private UserMetaRepository $userMetaRepository,
        private SubscriptionsRepository $subscriptionsRepository,
    ) {
        parent::__construct($lazyWidgetManager);
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
        $this->template->today = $this->getSubscriptionsFrom(new \DateTime('today midnight'));
        $this->template->last7days = $this->getSubscriptionsFrom(new \DateTime('-7 days'));
        $this->template->last30days = $this->getSubscriptionsFrom(new \DateTime('-30 days'));
        $this->template->setFile(__DIR__ . '/' . $this->templateName);
        $this->template->render();
    }

    private function getSubscriptionsFrom(\DateTime $createdFrom): array
    {
        $subscriptions = $this->subscriptionsRepository->getTable()
            ->where('subscriptions.start_time <= ?', new \DateTime())
            ->where('subscriptions.end_time > ?', new \DateTime())
            ->where('subscriptions.created_at > ?', $createdFrom)
            ->where([
                'subscription_type:subscription_type_content_access.content_access.name' => $this->contentAccessNames,
            ])
            ->order('subscriptions.created_at DESC');

        $haveAddress = $this->addressesRepository->all()->where(['type' => $this->addressTypes])->select('user_id')->fetchAssoc('user_id=user_id');
        if ($haveAddress) {
            $subscriptions->where('subscriptions.user_id NOT IN (?)', $haveAddress);
        }
        $haveDisabledNotification = $this->userMetaRepository->usersWithKey('notify_missing_print_address', 0)->select('user_id')->fetchAssoc('user_id=user_id');
        if ($haveDisabledNotification) {
            $subscriptions->where('subscriptions.user_id NOT IN (?)', $haveDisabledNotification);
        }

        return $subscriptions->fetchAll();
    }
}
