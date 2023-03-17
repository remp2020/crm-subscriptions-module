<?php

namespace Crm\SubscriptionsModule\Extension;

use Crm\ApplicationModule\NowTrait;
use Nette\Database\Table\ActiveRow;

/**
 * Starts immediately. No extending.
 */
class StartNowExtension implements ExtensionInterface
{
    use NowTrait;

    final public const METHOD_CODE = 'start_now';
    public const METHOD_NAME = 'Start now';

    public function getStartTime(ActiveRow $user, ActiveRow $subscriptionType): Extension
    {
        return new Extension($this->getNow());
    }
}
