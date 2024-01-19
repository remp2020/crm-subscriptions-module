<?php

namespace Crm\SubscriptionsModule\Scenarios;

use Crm\ApplicationModule\Models\Criteria\ScenarioParams\StringLabeledArrayParam;
use Crm\ApplicationModule\Models\Criteria\ScenariosCriteriaInterface;
use Crm\SubscriptionsModule\Repositories\ContentAccessRepository;
use Nette\Database\Table\ActiveRow;
use Nette\Database\Table\Selection;
use Nette\Utils\Random;

class ContentAccessCriteria implements ScenariosCriteriaInterface
{
    public const KEY = 'content_access';

    public function __construct(
        private ContentAccessRepository $contentAccessRepository,
    ) {
    }

    public function params(): array
    {
        $contentAccess = $this->contentAccessRepository->all()->fetchPairs('name', 'description');

        return [
            new StringLabeledArrayParam(self::KEY, 'Content access', $contentAccess),
        ];
    }

    public function addConditions(Selection $selection, array $paramValues, ActiveRow $criterionItemRow): bool
    {
        $values = $paramValues[self::KEY];
        if (!count($values->selection)) {
            return true;
        }

        $suffix = Random::generate();
        $stcaAlias = "stca_{$suffix}";
        $contentAccessAlias = "content_access_{$suffix}";

        $selection->alias("subscription_type:subscription_type_content_access", $stcaAlias);
        $selection->alias("{$stcaAlias}.content_access", $contentAccessAlias);

        // ignore operator, assume OR for now
        $selection->where("{$contentAccessAlias}.name IN (?)", $values->selection);

        return true;
    }

    public function label(): string
    {
        return 'Content access';
    }
}
