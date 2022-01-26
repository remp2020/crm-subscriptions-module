<?php

namespace Crm\SubscriptionsModule\Report;

use Nette\Database\Context;

interface ReportInterface
{
    public function injectDatabase(Context $db);

    public function getData(ReportGroup $group, $params);
}
