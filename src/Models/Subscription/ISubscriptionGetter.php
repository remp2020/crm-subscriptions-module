<?php

namespace Crm\UsersModule\User;

use Nette\Database\Table\ActiveRow;

interface ISubscriptionGetter
{
    public function getSubscription(): ActiveRow;
}
