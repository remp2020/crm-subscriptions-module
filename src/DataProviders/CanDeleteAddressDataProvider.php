<?php

namespace Crm\SubscriptionsModule\DataProviders;

use Contributte\Translation\Translator;
use Crm\ApplicationModule\DataProvider\DataProviderException;
use Crm\UsersModule\DataProviders\CanDeleteAddressDataProviderInterface;
use Nette\Application\LinkGenerator;

class CanDeleteAddressDataProvider implements CanDeleteAddressDataProviderInterface
{
    private $translator;

    private $linkGenerator;

    public function __construct(
        Translator $translator,
        LinkGenerator $linkGenerator
    ) {
        $this->translator = $translator;
        $this->linkGenerator = $linkGenerator;
    }

    public function provide(array $params): array
    {
        if (!isset($params['address'])) {
            throw new DataProviderException('address param missing');
        }

        $subscriptions = $params['address']->related('subscriptions')->fetchAll();
        if (count($subscriptions) > 0) {
            $listSubscriptions = array_map(function ($subscription) {
                $link = $this->linkGenerator->link('Subscriptions:SubscriptionsAdmin:edit', [
                    'id' => $subscription->id,
                    'userId' => $subscription->user_id
                ]);

                return "<a target='_blank' href='{$link}'><i class='fa fa-edit'></i>{$subscription->id}</a>";
            }, $subscriptions);

            return [
                'canDelete' => false,
                'message' => $this->translator->translate(
                    'subscriptions.admin.address.cant_delete',
                    count($subscriptions),
                    [ 'subscriptions' => implode(', ', $listSubscriptions) ]
                )
            ];
        }

        return [
            'canDelete' => true
        ];
    }
}
