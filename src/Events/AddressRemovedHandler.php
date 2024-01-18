<?php

namespace Crm\SubscriptionsModule\Events;

use Crm\SubscriptionsModule\Repositories\SubscriptionsRepository;
use Crm\UsersModule\Events\IAddressEvent;
use League\Event\AbstractListener;
use League\Event\EventInterface;

class AddressRemovedHandler extends AbstractListener
{
    private $subscriptionsRepository;

    public function __construct(SubscriptionsRepository $subscriptionsRepository)
    {
        $this->subscriptionsRepository = $subscriptionsRepository;
    }

    public function handle(EventInterface $event)
    {
        if (!($event instanceof IAddressEvent)) {
            throw new \Exception("invalid type of event received: " . get_class($event));
        }

        $address = $event->getAddress();

        foreach ($this->subscriptionsRepository->allWithAddress($address->id) as $subscription) {
            $this->subscriptionsRepository->update($subscription, [
                'address_id' => null,
            ]);
        }
    }
}
