<?php

namespace Crm\SubscriptionsModule\Api\v1;

use Crm\ApiModule\Models\Api\ApiHandler;
use Crm\SubscriptionsModule\Repositories\SubscriptionsRepository;
use Crm\UsersModule\Models\Auth\UsersApiAuthorizationInterface;
use Nette\Http\Response;
use Nette\Utils\DateTime;
use Tomaj\NetteApi\Params\GetInputParam;
use Tomaj\NetteApi\Response\JsonApiResponse;
use Tomaj\NetteApi\Response\ResponseInterface;

class UsersSubscriptionsHandler extends ApiHandler
{
    private $subscriptionsRepository;

    public function __construct(
        SubscriptionsRepository $subscriptionsRepository
    ) {
        $this->subscriptionsRepository = $subscriptionsRepository;
    }

    public function params(): array
    {
        return [
            new GetInputParam('show_finished'),
        ];
    }

    public function handle(array $params): ResponseInterface
    {
        $authorization = $this->getAuthorization();
        if (!($authorization instanceof UsersApiAuthorizationInterface)) {
            throw new \Exception("Wrong authorization service used. Should be 'UsersApiAuthorizationInterface'");
        }

        $authorizedUsers = $authorization->getAuthorizedUsers();

        $where = ['end_time >= ?' => new DateTime()];

        $showFinished = filter_var($params['show_finished'], FILTER_VALIDATE_BOOL);
        if ($showFinished) {
            $where = [];
        }

        $subscriptions = [];
        foreach ($authorizedUsers as $authorizedUser) {
            $subscriptions[] = $this->subscriptionsRepository->userSubscriptions($authorizedUser->id)->where($where)->fetchAll();
        }
        $subscriptions = array_merge([], ...$subscriptions);
        usort($subscriptions, function ($a, $b) {
            return ($a->end_time <=> $b->end_time) * -1;
        });

        $result = [
            'status' => 'ok',
            'subscriptions' => [],
        ];

        foreach ($subscriptions as $subscription) {
            $subscriptionType = $subscription->subscription_type;
            $result['subscriptions'][] = $this->formatSubscription($subscription, $subscriptionType);
        }

        $response = new JsonApiResponse(Response::S200_OK, $result);
        return $response;
    }

    private function formatSubscription($subscription, $subscriptionType)
    {
        $access = [];
        foreach ($subscriptionType->related('subscription_type_content_access')->order('content_access.sorting') as $contentAccess) {
            $access[] = $contentAccess->content_access->name;
        }

        return [
            'id' => $subscription->id,
            'start_at' => $subscription->start_time->format('c'),
            'end_at' => $subscription->end_time->format('c'),
            'code' => $subscriptionType->code,
            'access' => $access,
            'name' => $subscriptionType->name,
            'label' => $subscriptionType->user_label,
        ];
    }
}
