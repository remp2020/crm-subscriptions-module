<?php

declare(strict_types=1);

namespace Crm\SubscriptionsModule\Scenarios;

use Contributte\Translation\Translator;
use Crm\ApplicationModule\Models\Criteria\ScenarioParams\StringLabeledArrayParam;
use Crm\ApplicationModule\Models\Criteria\ScenariosCriteriaInterface;
use Crm\SubscriptionsModule\Repositories\SubscriptionTypeTagsRepository;
use Exception;
use Nette\Database\Table\ActiveRow;
use Nette\Database\Table\Selection;
use Nette\Utils\Random;

class SubscriptionTypeHasTagCriteria implements ScenariosCriteriaInterface
{
    public const KEY = 'subscription_type_has_tag';

    public function __construct(
        private SubscriptionTypeTagsRepository $subscriptionTypeTagsRepository,
        private Translator $translator,
    ) {
    }

    public function params(): array
    {
        $tags = $this->subscriptionTypeTagsRepository->tagsSortedByOccurrences();

        return [
            new StringLabeledArrayParam(self::KEY, $this->label(), $tags),
        ];
    }

    public function addConditions(Selection $selection, array $paramValues, ActiveRow $criterionItemRow): bool
    {
        $values = $paramValues[self::KEY];
        if (empty($values->selection)) {
            throw new Exception("Empty selection is not allowed for " . self::KEY);
        }

        $suffix = Random::generate();
        $tagsAlias = "subscription_type_tags_{$suffix}";

        $selection->alias("subscription_type:subscription_type_tags", $tagsAlias);
        $selection->where("{$tagsAlias}.tag IN (?)", $values->selection);

        return true;
    }

    public function label(): string
    {
        return $this->translator->translate('subscriptions.admin.scenarios.subscription_type_has_tag.label');
    }
}
