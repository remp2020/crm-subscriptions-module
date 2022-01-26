<?php

namespace Crm\UsersModule\User;

use Nette\Database\Table\IRow;

interface ISubscriptionGetter
{
    public function getSubscription(): IRow;
}
