<?php

namespace Crm\SubscriptionsModule\Helpers;

use Nette\Utils\Html;

class TypeContentHelper
{
    public function process($type)
    {
        $result = '';

        foreach ($type->related('subscription_type_content_access')->order('content_access.sorting') as $subscriptionTypeContentAccess) {
            $contentType = $subscriptionTypeContentAccess->content_access;
            $result .= Html::el('span', ['class' => $contentType->class])->setText($contentType->description) . ' ';
        }

        return $result;
    }
}
