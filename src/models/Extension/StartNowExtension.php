<?php

namespace Crm\SubscriptionsModule\Extension;

use Nette\Database\Table\ActiveRow;
use Nette\Utils\DateTime;

class StartNowExtension implements ExtensionInterface
{
    public const METHOD_CODE = 'start_now';

    public const METHOD_NAME = 'Start now';

    public function getStartTime(ActiveRow $user, ActiveRow $subscriptionType)
    {
        return new Extension(new DateTime());
    }
}
