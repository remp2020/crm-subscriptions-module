<?php

namespace Crm\SubscriptionsModule\Report;

use Nette\Database\Explorer;

class ReportTable
{
    private $params;

    private $db;

    private $reportGroup;

    private $reports = [];

    private $belongs = [];

    public function __construct(array $params, Explorer $db, ReportGroup $reportGroup)
    {
        $this->params = $params;
        $this->db = $db;
        $this->reportGroup = $reportGroup;
    }

    public function addReport(ReportInterface $report, array $belongIn = [])
    {
        $report->injectDatabase($this->db);
        $this->reports[] = $report;
        if (count($belongIn) > 0) {
            foreach ($belongIn as $belongs) {
                if (!isset($this->belongs[$belongs])) {
                    $this->belongs[$belongs] = [];
                }
                $this->belongs[$belongs][] = $report;
            }
        }
        return $this;
    }

    public function getData()
    {
        $result = [];
        foreach ($this->reports as $report) {
            $result[] = $report->getData($this->reportGroup, $this->params);
        }

        foreach ($result as &$row) {
            $key = $row['key'];
            if (isset($this->belongs[$key])) {
                $totalData = $row['data'];

                foreach ($this->belongs[$key] as $sub) {
                    foreach ($result as $r) {
                        if ($r['id'] == $sub->getId()) {
                            foreach ($r['data'] as $key => $value) {
                                $totalData[$key] = $totalData[$key] - $value;
                            }
                        }
                    }
                }
                $row['check'] = $totalData;
            }
        }

        return $result;
    }
}
