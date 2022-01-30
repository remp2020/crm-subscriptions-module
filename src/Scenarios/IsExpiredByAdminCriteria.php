<?php

namespace Crm\SubscriptionsModule\Scenarios;

use Crm\ApplicationModule\Criteria\ScenarioParams\BooleanParam;
use Crm\ApplicationModule\Criteria\ScenariosCriteriaInterface;
use Crm\SubscriptionsModule\Subscription\StopSubscriptionHandler;
use Kdyby\Translation\Translator;
use Nette\Database\Table\ActiveRow;
use Nette\Database\Table\Selection;

class IsExpiredByAdminCriteria implements ScenariosCriteriaInterface
{
    public const KEY = 'is_expired_by_admin';

    private $translator;

    public function __construct(Translator $translator)
    {
        $this->translator = $translator;
    }

    public function params(): array
    {
        return [
            new BooleanParam('is_expired_by_admin', $this->label()),
        ];
    }

    public function addConditions(Selection $selection, array $paramValues, ActiveRow $criterionItemRow): bool
    {
        $values = $paramValues['is_expired_by_admin'];

        if ($values->selection) {
            $selection->where([
                ':subscriptions_meta.key' => StopSubscriptionHandler::META_KEY_EXPIRED_BY_ADMIN,
                ':subscriptions_meta.value' => 1
            ]);
        } else {
            $selection->joinWhere(
                ':subscriptions_meta',
                ":subscriptions_meta.key = ?",
                StopSubscriptionHandler::META_KEY_EXPIRED_BY_ADMIN
            )
                ->where(':subscriptions_meta.id IS NULL OR :subscriptions_meta.value = 0');
        }

        return true;
    }

    public function label(): string
    {
        return $this->translator->translate('subscriptions.admin.scenarios.is_expired_by_admin.label');
    }
}
