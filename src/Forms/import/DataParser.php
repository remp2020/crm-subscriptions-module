<?php

namespace Crm\SubscriptionsModule\Forms;

class DataParser
{
    private $delimiter;

    public function __construct($delimiter = ';')
    {
        $this->delimiter = $delimiter;
    }

    public function getData($data)
    {
        $rows = explode("\n", $data);
        $keys = [];
        if (count($rows) > 0) {
            $keys = explode($this->delimiter, $rows[0]);
        }

        $counter = 0;
        $result = [];
        foreach ($rows as $row) {
            $counter++;
            if ($counter == 1) {
                continue;
            }

            $parts = explode($this->delimiter, $row);
            $i = 0;
            $resultRow = [];
            foreach ($parts as $part) {
                $resultRow[ $keys[$i] ] = $part;
                $i++;
            }

            $result[] = $resultRow;
        }

        return $result;
    }
}
