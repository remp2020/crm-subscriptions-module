<?php

namespace Crm\SubscriptionsModule\Api\v1;

use Crm\ApiModule\Api\ApiHandler;
use Crm\ApiModule\Api\JsonResponse;
use Crm\ApiModule\Authorization\ApiAuthorizationInterface;
use Crm\ApiModule\Params\InputParam;
use Crm\ApiModule\Params\ParamsProcessor;
use Crm\SubscriptionsModule\Repository\SubscriptionsRepository;
use Crm\UsersModule\Auth\UsersApiAuthorizationInterface;
use Crm\UsersModule\Repository\AccessTokensRepository;
use Nette\Http\Response;
use Nette\Utils\DateTime;

class UsersSubscriptionsHandler extends ApiHandler
{
    private $subscriptionsRepository;

    private $accessTokensRepository;

    public function __construct(
        SubscriptionsRepository $subscriptionsRepository,
        AccessTokensRepository $accessTokensRepository
    ) {
        $this->subscriptionsRepository = $subscriptionsRepository;
        $this->accessTokensRepository = $accessTokensRepository;
    }

    public function params()
    {
        return [
            new InputParam(InputParam::TYPE_GET, 'show_finished', InputParam::OPTIONAL),
        ];
    }

    public function handle(ApiAuthorizationInterface $authorization)
    {
        if (!($authorization instanceof UsersApiAuthorizationInterface)) {
            throw new \Exception("Wrong authorization service used. Should be 'UsersApiAuthorizationInterface'");
        }

        $data = $authorization->getAuthorizedData();
        if (!isset($data['token'])) {
            $response = new JsonResponse(['status' => 'error', 'message' => 'Cannot authorize user']);
            $response->setHttpCode(Response::S403_FORBIDDEN);
            return $response;
        }

        $paramsProcessor = new ParamsProcessor($this->params());
        $params = $paramsProcessor->getValues();

        $where = ['end_time >= ?' => new DateTime()];
        if (isset($params['show_finished']) && in_array($params['show_finished'], ['1', 'true'])) {
            $where = [];
        }

        $subscriptions = [];
        $authorizedUsers = $authorization->getAuthorizedUsers();
        foreach ($authorizedUsers as $authorizedUser) {
            $subscriptions[] = $this->subscriptionsRepository->userSubscriptions($authorizedUser->id)->where($where)->fetchAll();
        }
        $subscriptions = array_merge([], ...$subscriptions);
        usort($subscriptions, function ($a, $b) {
            return $a->end_time < $b->end_time;
        });

        $result = [
            'status' => 'ok',
            'subscriptions' => [],
        ];

        foreach ($subscriptions as $subscription) {
            $subscriptionType = $subscription->subscription_type;
            $result['subscriptions'][] = $this->formatSubscription($subscription, $subscriptionType);
        }

        $response = new JsonResponse($result);
        $response->setHttpCode(Response::S200_OK);
        return $response;
    }

    private function formatSubscription($subscription, $subscriptionType)
    {
        $access = [];
        foreach ($subscriptionType->related('content_access')->order('content_access.sorting') as $contentAccess) {
            $access[] = $contentAccess->content_access->name;
        }

        return [
            'id' => $subscription->id,
            'start_at' => $subscription->start_time->format('c'),
            'end_at' => $subscription->end_time->format('c'),
            'code' => $subscriptionType->code,
            'access' => $access,
        ];
    }
}
