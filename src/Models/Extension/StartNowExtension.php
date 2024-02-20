<?php

namespace Crm\SubscriptionsModule\Models\Extension;

use Crm\ApplicationModule\Models\NowTrait;
use Nette\Database\Table\ActiveRow;

/**
 * Starts immediately. No extending.
 */
class StartNowExtension implements ExtensionInterface
{
    use NowTrait;

    public const METHOD_CODE = 'start_now';
    public const METHOD_NAME = 'Start now';

    public function getStartTime(ActiveRow $user, ActiveRow $subscriptionType, ?ActiveRow $address = null): Extension
    {
        return new Extension($this->getNow());
    }
}
