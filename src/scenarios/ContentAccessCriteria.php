<?php

namespace Crm\SubscriptionsModule\Scenarios;

use Crm\ApplicationModule\Criteria\ScenarioParams\StringLabeledArrayParam;
use Crm\ApplicationModule\Criteria\ScenariosCriteriaInterface;
use Crm\SubscriptionsModule\Repository\ContentAccessRepository;
use Nette\Database\Table\IRow;
use Nette\Database\Table\Selection;

class ContentAccessCriteria implements ScenariosCriteriaInterface
{
    private $contentAccessRepository;

    public function __construct(
        ContentAccessRepository $contentAccessRepository
    ) {
        $this->contentAccessRepository = $contentAccessRepository;
    }

    public function params(): array
    {
        $contentAccess = $this->contentAccessRepository->all()->fetchPairs('name', 'description');

        return [
            new StringLabeledArrayParam('content_access', 'Content access', $contentAccess),
        ];
    }

    public function addConditions(Selection $selection, array $paramValues, IRow $criterionItemRow): bool
    {
        $values = $paramValues['content_access'];

        // ignore operator, assume OR for now
        $selection->where('subscription_type:subscription_type_content_access.content_access.name IN (?)', $values->selection);

        return true;
    }

    public function label(): string
    {
        return 'Content access';
    }
}
