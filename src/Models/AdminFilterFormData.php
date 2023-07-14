<?php

namespace Crm\SubscriptionsModule\Models;

use Crm\SubscriptionsModule\Repository\SubscriptionTypeTagsRepository;
use Crm\SubscriptionsModule\Repository\SubscriptionTypesRepository;
use Nette\Database\Table\Selection;

class AdminFilterFormData
{
    private array $formData;

    public function __construct(
        private SubscriptionTypesRepository $subscriptionTypesRepository,
        private SubscriptionTypeTagsRepository $subscriptionTypeTagsRepository,
    ) {
    }

    public function parse(array $formData): void
    {
        $this->formData = $formData;
    }

    public function getFilteredSubscriptionTypes(): Selection
    {
        $subscriptionTypes = $this->subscriptionTypesRepository
            ->all()
            ->select('subscription_types.*')
            ->group('subscription_types.id');

        if ($this->getName()) {
            $subscriptionTypes->where('subscription_types.name LIKE ?', "%{$this->getName()}%");
        }
        if ($this->getCode()) {
            $subscriptionTypes->where('subscription_types.code LIKE ?', "%{$this->getCode()}%");
        }
        if ($this->getContentAccesses()) {
            // match all selected content accesses
            foreach ($this->getContentAccesses() as $contentAccess) {
                $stcaAlias = "stca_{$contentAccess}";
                $contentAccessAlias = "ca_{$contentAccess}";

                // kung fu to ensure there's separate join for each content access
                $subscriptionTypes
                    ->alias(":subscription_type_content_access", $stcaAlias)
                    ->alias(".$stcaAlias.content_access", $contentAccessAlias)
                    ->joinWhere($contentAccessAlias, "{$contentAccessAlias}.name = ?", $contentAccess)
                    ->where("{$contentAccessAlias}.id IS NOT NULL");
            }
        }
        if ($this->getPriceFrom()) {
            $subscriptionTypes->where('subscription_types.price >= ?', $this->getPriceFrom());
        }
        if ($this->getPriceTo()) {
            $subscriptionTypes->where('subscription_types.price <= ?', $this->getPriceTo());
        }
        if ($this->getLengthFrom()) {
            $subscriptionTypes->where('subscription_types.length >= ?', $this->getLengthFrom());
        }
        if ($this->getLengthTo()) {
            $subscriptionTypes->where('subscription_types.length <= ?', $this->getLengthTo());
        }
        if ($this->getDefault()) {
            $subscriptionTypes->where('subscription_types.default = ?', $this->getDefault());
        }

        if ($this->getTags()) {
            $tagsTable = $this->subscriptionTypeTagsRepository->getTable();

            // Get every id that has all the tags we want to filter for
            $filteredIds = $tagsTable
                ->select('subscription_type_id')
                ->where('tag', $this->getTags())
                ->group('subscription_type_id')
                ->having('COUNT(DISTINCT tag) = ?', count($this->getTags()));

            $subscriptionTypes->where('subscription_types.id', $filteredIds);
        }

        return $subscriptionTypes;
    }

    public function getFormValues()
    {
        return [
            'name' => $this->getName(),
            'code' => $this->getCode(),
            'content_access' => $this->getContentAccesses(),
            'default' => $this->getDefault(),
            'price_from' => $this->getPriceFrom(),
            'price_to' => $this->getPriceTo(),
            'length_from' => $this->getLengthFrom(),
            'length_to' => $this->getLengthTo(),
            'tag' => $this->getTags(),
        ];
    }

    private function getName(): ?string
    {
        return $this->formData['name'] ?? null;
    }

    private function getCode(): ?string
    {
        return $this->formData['code'] ?? null;
    }

    private function getContentAccesses(): ?array
    {
        return $this->formData['content_access'] ?? null;
    }

    private function getDefault(): ?bool
    {
        return $this->formData['default'] ?? null;
    }

    private function getPriceFrom(): ?float
    {
        return $this->formData['price_from'] ?? null;
    }

    private function getPriceTo(): ?float
    {
        return $this->formData['price_to'] ?? null;
    }

    private function getLengthFrom(): ?float
    {
        return $this->formData['length_from'] ?? null;
    }

    private function getLengthTo(): ?float
    {
        return $this->formData['length_to'] ?? null;
    }

    private function getTags(): ?array
    {
        return $this->formData['tag'] ?? null;
    }
}
