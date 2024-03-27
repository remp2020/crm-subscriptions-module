<?php

namespace Crm\SubscriptionsModule\Api\v1;

use Crm\ApiModule\Models\Api\ApiHandler;
use Crm\ApiModule\Models\Api\IdempotentHandlerInterface;
use Crm\SubscriptionsModule\Repositories\SubscriptionMetaRepository;
use Crm\SubscriptionsModule\Repositories\SubscriptionTypesRepository;
use Crm\SubscriptionsModule\Repositories\SubscriptionsRepository;
use Crm\UsersModule\Models\Auth\UserManager;
use Nette\Database\Table\ActiveRow;
use Nette\Http\IResponse;
use Nette\Http\Response;
use Nette\Utils\DateTime;
use Tomaj\NetteApi\Params\PostInputParam;
use Tomaj\NetteApi\Response\JsonApiResponse;
use Tomaj\NetteApi\Response\ResponseInterface;

class CreateSubscriptionHandler extends ApiHandler implements IdempotentHandlerInterface
{
    public function __construct(
        private SubscriptionTypesRepository $subscriptionTypesRepository,
        private SubscriptionsRepository $subscriptionsRepository,
        private SubscriptionMetaRepository $subscriptionMetaRepository,
        private UserManager $userManager
    ) {
        parent::__construct();
    }

    public function params(): array
    {
        return [
            (new PostInputParam('email'))->setRequired(),
            (new PostInputParam('subscription_type_id')),
            (new PostInputParam('subscription_type_code')),
            (new PostInputParam('is_paid'))->setRequired(),
            (new PostInputParam('start_time')),
            (new PostInputParam('end_time')),
            (new PostInputParam('type')),
            (new PostInputParam('note')),
        ];
    }

    public function handle(array $params): ResponseInterface
    {
        $result = $this->validateSubscriptionTypeInput($params);
        if ($result instanceof JsonApiResponse) {
            return $result;
        }

        $subscriptionType = null;
        if (isset($params['subscription_type_id'])) {
            $subscriptionType = $this->subscriptionTypesRepository->find($params['subscription_type_id']);
        }

        if (!$subscriptionType && isset($params['subscription_type_code'])) {
            $subscriptionType = $this->subscriptionTypesRepository->findByCode($params['subscription_type_code']);
        }

        if (!$subscriptionType) {
            $response = new JsonApiResponse(IResponse::S404_NotFound, ['status' => 'error', 'message' => 'Subscription type not found']);
            return $response;
        }

        $user = $this->userManager->loadUserByEmail($params['email']);

        if (!empty($subscriptionType->limit_per_user) &&
            $this->subscriptionsRepository->getCount($subscriptionType->id, $user->id) >= $subscriptionType->limit_per_user) {
            $response = new JsonApiResponse(IResponse::S400_BadRequest, ['status' => 'error', 'message' => 'Limit per user reached']);
            return $response;
        }

        $type = SubscriptionsRepository::TYPE_REGULAR;
        if (isset($params['type']) && in_array($params['type'], $this->subscriptionsRepository->activeSubscriptionTypes()->fetchPairs('type', 'type'), true)) {
            $type = $params['type'];
        }

        $startTime = null;
        if (isset($params['start_time'])) {
            $startTime = DateTime::from($params['start_time']);
        }
        $endTime = null;
        if (isset($params['end_time'])) {
            $endTime = DateTime::from($params['end_time']);
        }
        $note = $params['note'] ?? null;

        $subscription = $this->subscriptionsRepository->add(
            $subscriptionType,
            false,
            $params['is_paid'],
            $user,
            $type,
            $startTime,
            $endTime,
            $note
        );

        if ($this->idempotentKey()) {
            $this->subscriptionMetaRepository->setMeta($subscription, 'idempotent_key', $this->idempotentKey());
        }

        return $this->createResponse($subscription);
    }

    public function idempotentHandle(array $params): ResponseInterface
    {
        $subscription = $this->subscriptionMetaRepository->findSubscriptionBy('idempotent_key', $this->idempotentKey());

        return $this->createResponse($subscription);
    }

    private function createResponse(ActiveRow $subscription)
    {
        $response = new JsonApiResponse(Response::S200_OK, [
            'status' => 'ok',
            'message' => 'Subscription created',
            'subscriptions' => [
                'id' => $subscription->id,
                'subscription_type_id' => $subscription->subscription_type_id,
                'is_paid' => $subscription->is_paid,
                'start_time' => $subscription->start_time->format('c'),
                'end_time' => $subscription->end_time->format('c'),
                'type' => $subscription->type,
                'note' => $subscription->note,
            ],
        ]);
        return $response;
    }

    private function validateSubscriptionTypeInput($params)
    {
        if (isset($params['subscription_type_id']) || isset($params['subscription_type_code'])) {
            return true;
        }

        return new JsonApiResponse(IResponse::S400_BadRequest, [
            'status' => 'error',
            'code' => 'invalid_input',
            'errors' => [
                'subscription_type_id' => [
                    'Field is required if subscription_type_code is not present'
                ],
                'subscription_type_code' => [
                    'Field is required if subscription_type_id is not present'
                ]
            ]
        ]);
    }
}
