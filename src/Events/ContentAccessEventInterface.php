<?php

namespace Crm\SubscriptionsModule\Events;

use Nette\Database\Table\ActiveRow;

interface ContentAccessEventInterface
{
    public function getContentAccess(): ?ActiveRow;
}
