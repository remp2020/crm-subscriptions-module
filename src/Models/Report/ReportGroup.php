<?php

namespace Crm\SubscriptionsModule\Models\Report;

class ReportGroup
{
    private $groupField;

    public function __construct($groupField)
    {
        $this->groupField = $groupField;
    }

    public function groupField()
    {
        return $this->groupField;
    }
}
