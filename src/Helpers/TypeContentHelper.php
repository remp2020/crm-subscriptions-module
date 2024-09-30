<?php

namespace Crm\SubscriptionsModule\Helpers;

use Latte\ContentType;
use Latte\Runtime\FilterInfo;
use Nette\Utils\Html;

class TypeContentHelper
{
    public function process(FilterInfo $filterInfo, $type)
    {
        $filterInfo->contentType = ContentType::Html;
        $result = '';

        foreach ($type->related('subscription_type_content_access')->order('content_access.sorting') as $subscriptionTypeContentAccess) {
            $contentType = $subscriptionTypeContentAccess->content_access;
            $result .= Html::el('span', ['class' => $contentType->class])->setText($contentType->description) . ' ';
        }

        return $result;
    }
}
