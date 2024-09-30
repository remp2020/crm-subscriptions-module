<?php

namespace Crm\SubscriptionsModule\Helpers;

use Crm\SubscriptionsModule\Repositories\SubscriptionsRepository;
use Latte\ContentType;
use Latte\Runtime\FilterInfo;
use Nette\Utils\Html;

class TypeLabel
{
    public function process(FilterInfo $filterInfo, $type)
    {
        $filterInfo->contentType = ContentType::Html;

        if ($type == SubscriptionsRepository::TYPE_REGULAR) {
            return Html::el('span', ['class' => 'label label-success'])->setText($type);
        }

        if ($type == SubscriptionsRepository::TYPE_FREE) {
            return Html::el('span', ['class' => 'label label-warning'])->setText($type);
        }

        if ($type == SubscriptionsRepository::TYPE_DONATION) {
            return Html::el('span', ['class' => 'label label-warning'])->setText($type);
        }

        if ($type == SubscriptionsRepository::TYPE_PREPAID) {
            return Html::el('span', ['class' => 'label label-danger'])->setText($type);
        }

        return Html::el('span', ['class' => 'label label-default'])->setText($type);
    }
}
