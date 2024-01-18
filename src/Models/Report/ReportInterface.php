<?php

namespace Crm\SubscriptionsModule\Models\Report;

use Nette\Database\Explorer;

interface ReportInterface
{
    public function injectDatabase(Explorer $db);

    public function getData(ReportGroup $group, $params);
}
