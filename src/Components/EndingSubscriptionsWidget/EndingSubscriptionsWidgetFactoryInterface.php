<?php

namespace Crm\SubscriptionsModule\Components\EndingSubscriptionsWidget;

interface EndingSubscriptionsWidgetFactoryInterface
{
    public function create(): EndingSubscriptionsWidget;
}
