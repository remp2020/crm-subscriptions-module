<?php

namespace Crm\SubscriptionsModule\Api\v1;

use Crm\ApiModule\Api\ApiHandler;
use Crm\ApiModule\Api\IdempotentHandlerInterface;
use Crm\ApiModule\Api\JsonResponse;
use Crm\ApiModule\Authorization\ApiAuthorizationInterface;
use Crm\ApiModule\Params\InputParam;
use Crm\ApiModule\Params\ParamsProcessor;
use Crm\ApiModule\Response\ApiResponseInterface;
use Crm\SubscriptionsModule\Repository\SubscriptionMetaRepository;
use Crm\SubscriptionsModule\Repository\SubscriptionTypesRepository;
use Crm\SubscriptionsModule\Repository\SubscriptionsRepository;
use Crm\UsersModule\Auth\UserManager;
use Nette\Database\Table\ActiveRow;
use Nette\Http\Response;
use Nette\Utils\DateTime;

class CreateSubscriptionHandler extends ApiHandler implements IdempotentHandlerInterface
{
    private $subscriptionsRepository;

    private $subscriptionTypesRepository;

    private $subscriptionMetaRepository;

    private $userManager;

    public function __construct(
        SubscriptionTypesRepository $subscriptionTypesRepository,
        SubscriptionsRepository $subscriptionsRepository,
        SubscriptionMetaRepository $subscriptionMetaRepository,
        UserManager $userManager
    ) {
        $this->subscriptionTypesRepository = $subscriptionTypesRepository;
        $this->subscriptionsRepository = $subscriptionsRepository;
        $this->subscriptionMetaRepository = $subscriptionMetaRepository;
        $this->userManager = $userManager;
    }

    public function params(): array
    {
        return [
            new InputParam(InputParam::TYPE_POST, 'email', InputParam::REQUIRED),
            new InputParam(InputParam::TYPE_POST, 'subscription_type_id', InputParam::REQUIRED),
            new InputParam(InputParam::TYPE_POST, 'is_paid', InputParam::REQUIRED),
            new InputParam(InputParam::TYPE_POST, 'start_time', InputParam::OPTIONAL),
            new InputParam(InputParam::TYPE_POST, 'end_time', InputParam::OPTIONAL),
            new InputParam(InputParam::TYPE_POST, 'type', InputParam::OPTIONAL),
            new InputParam(InputParam::TYPE_POST, 'note', InputParam::OPTIONAL),
        ];
    }

    public function handle(array $params): ApiResponseInterface
    {
        $paramsProcessor = new ParamsProcessor($this->params());
        $params = $paramsProcessor->getValues();

        $subscriptionType = $this->subscriptionTypesRepository->find($params['subscription_type_id']);
        if (!$subscriptionType) {
            $response = new JsonResponse(['status' => 'error', 'message' => 'Subscription type not found']);
            $response->setHttpCode(Response::S404_NOT_FOUND);
            return $response;
        }

        $user = $this->userManager->loadUserByEmail($params['email']);

        if (!empty($subscriptionType->limit_per_user) &&
            $this->subscriptionsRepository->getCount($subscriptionType->id, $user->id) >= $subscriptionType->limit_per_user) {
            $response = new JsonResponse(['status' => 'error', 'message' => 'Limit per user reached']);
            $response->setHttpCode(Response::S400_BAD_REQUEST);
            return $response;
        }

        $type = SubscriptionsRepository::TYPE_REGULAR;
        if (isset($params['type']) && in_array($params['type'], $this->subscriptionsRepository->activeSubscriptionTypes()->fetchPairs('type', 'type'))) {
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

    public function idempotentHandle(ApiAuthorizationInterface $authorization)
    {
        $subscription = $this->subscriptionMetaRepository->findSubscriptionBy('idempotent_key', $this->idempotentKey());

        return $this->createResponse($subscription);
    }

    private function createResponse(ActiveRow $subscription)
    {
        $response = new JsonResponse([
            'status' => 'ok',
            'message' => 'Subscription created',
            'subscriptions' => [
                'id' => $subscription->id,
                'start_time' => $subscription->start_time->format('c'),
                'end_time' => $subscription->end_time->format('c'),
            ],
        ]);
        $response->setHttpCode(Response::S200_OK);
        return $response;
    }
}
