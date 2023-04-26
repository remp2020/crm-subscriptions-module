<?php

namespace Crm\SubscriptionsModule\Api\v1;

use Crm\ApiModule\Api\ApiHandler;
use Crm\SubscriptionsModule\Repository\SubscriptionTypesRepository;
use Crm\SubscriptionsModule\Repository\SubscriptionsRepository;
use Nette\Http\IResponse;
use Nette\Utils\DateTime;
use Tomaj\NetteApi\Params\PostInputParam;
use Tomaj\NetteApi\Response\JsonApiResponse;
use Tomaj\NetteApi\Response\ResponseInterface;

class UpdateSubscriptionHandler extends ApiHandler
{
    public function __construct(
        private SubscriptionTypesRepository $subscriptionTypesRepository,
        private SubscriptionsRepository $subscriptionsRepository
    ) {
        parent::__construct();
    }

    public function params(): array
    {
        return [
            (new PostInputParam('id'))->setRequired(),
            (new PostInputParam('subscription_type_id')),
            (new PostInputParam('is_paid')),
            (new PostInputParam('start_time')),
            (new PostInputParam('end_time')),
            (new PostInputParam('type')),
            (new PostInputParam('note')),
        ];
    }

    public function handle(array $params): ResponseInterface
    {
        $subscription = $this->subscriptionsRepository->find($params['id']);
        if (!$subscription) {
            return new JsonApiResponse(IResponse::S404_NotFound, [
                'status' => 'error',
                'code' => 'subscription_not_found',
                'message' => 'Subscription not found: ' . $params['id'],
            ]);
        }

        $data = [];
        if (isset($params['subscription_type_id'])) {
            $subscriptionType = $this->subscriptionTypesRepository->find($params['subscription_type_id']);
            if (!$subscriptionType) {
                return new JsonApiResponse(IResponse::S404_NotFound, [
                    'status' => 'error',
                    'code' => 'subscription_type_not_found',
                    'message' => 'Subscription type not found: ' . $params['subscription_type_id'],
                ]);
            }

            $data['subscription_type_id'] = $subscriptionType->id;
        }

        if (isset($params['is_paid'])) {
            $data['is_paid'] = filter_var($params['is_paid'], FILTER_VALIDATE_BOOL);
        }

        if (isset($params['start_time'])) {
            $data['start_time'] = DateTime::createFromFormat(DateTime::RFC3339, $params['start_time']);
            if ($data['start_time'] === false) {
                return new JsonApiResponse(IResponse::S400_BadRequest, [
                    'status' => 'error',
                    'code' => 'start_time_wrong_format',
                    'message' => 'Start time has wrong format - RFC3339 format required',
                ]);
            }
        }

        if (isset($params['end_time'])) {
            $data['end_time'] = DateTime::createFromFormat(DateTime::RFC3339, $params['end_time']);
            if ($data['end_time'] === false) {
                return new JsonApiResponse(IResponse::S400_BadRequest, [
                    'status' => 'error',
                    'code' => 'end_time_wrong_format',
                    'message' => 'End time has wrong format - RFC3339 format required',
                ]);
            }
        }

        if (isset($params['type'])) {
            if (!in_array($params['type'], $this->subscriptionsRepository->activeSubscriptionTypes()->fetchPairs('type', 'type'), true)) {
                return new JsonApiResponse(IResponse::S400_BadRequest, [
                    'status' => 'error',
                    'code' => 'wrong_type',
                    'Wrong type: ' . $params['type']
                ]);
            }
            $data['type'] = $params['type'];
        }

        if (isset($params['note'])) {
            $data['note'] = $params['note'];
        }

        $this->subscriptionsRepository->update($subscription, $data);

        $subscription = $this->subscriptionsRepository->find($subscription->id);

        return new JsonApiResponse(IResponse::S200_OK, [
            'status' => 'ok',
            'message' => 'Subscription updated',
            'subscription' => [
                'id' => $subscription->id,
                'subscription_type_id' => $subscription->subscription_type_id,
                'is_paid' => $subscription->is_paid,
                'start_time' => $subscription->start_time->format('c'),
                'end_time' => $subscription->end_time->format('c'),
                'type' => $subscription->type,
                'note' => $subscription->note,
            ],
        ]);
    }
}
