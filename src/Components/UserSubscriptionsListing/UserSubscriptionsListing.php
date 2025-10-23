<?php

namespace Crm\SubscriptionsModule\Components\UserSubscriptionsListing;

use Crm\ApplicationModule\Components\AjaxDataPaginator\PaginatedComponent;
use Crm\ApplicationModule\Components\AjaxDataPaginator\PaginatesDataTrait;
use Crm\ApplicationModule\Models\Widget\BaseLazyWidget;
use Crm\ApplicationModule\Models\Widget\DetailWidgetInterface;
use Crm\ApplicationModule\Models\Widget\LazyWidgetManager;
use Crm\SubscriptionsModule\Repositories\SubscriptionsRepository;
use Nette\Localization\Translator;

/**
 * This component fetches specific user's subscriptions and renders
 * data table. Used in user detail.
 *
 * @package Crm\SubscriptionsModule\Components
 */
class UserSubscriptionsListing extends BaseLazyWidget implements DetailWidgetInterface, PaginatedComponent
{
    use PaginatesDataTrait;

    private string $templateName = 'user_subscriptions_listing.latte';

    /** @var ?int Total subscription count for current user */
    private ?int $totalCount = null;

    public function __construct(
        LazyWidgetManager $lazyWidgetManager,
        private readonly SubscriptionsRepository $subscriptionsRepository,
        private readonly Translator $translator,
    ) {
        parent::__construct($lazyWidgetManager);
    }

    public function header(string $id = ''): string
    {
        $header = $this->translator->translate('subscriptions.admin.user_subscriptions.header');
        if ($id) {
            $header .= ' <small>(' . $this->totalCount($id) . ')</small>';
        }
        return $header;
    }

    public function identifier(): string
    {
        return 'usersubscriptions';
    }

    public function render(int $id): void
    {
        $this->entityId = $id;

        $totalSubscriptions = $this->totalCount($this->entityId);

        $paginator = $this->getAjaxPaginator(
            snippetName: 'subscriptionsTable',
            itemCount: $totalSubscriptions,
        );

        $subscriptions = $this->subscriptionsRepository
            ->userSubscriptions($this->entityId)
            ->limit($paginator->getLimit(), $paginator->getOffset());

        $this->template->totalSubscriptions = $totalSubscriptions;
        $this->template->subscriptions = $subscriptions;
        $this->template->entityId = $this->entityId;
        $this->template->paginator = $paginator;

        $this->template->setFile(__DIR__ . '/' . $this->templateName);
        $this->template->render();
    }

    private function totalCount(int $userId): int
    {
        return $this->totalCount ??= $this->subscriptionsRepository
            ->userSubscriptions($userId)
            ->count();
    }
}
