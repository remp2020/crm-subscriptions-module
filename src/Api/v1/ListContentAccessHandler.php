<?php

namespace Crm\SubscriptionsModule\Api\v1;

use Crm\ApiModule\Api\ApiHandler;
use Crm\ApiModule\Api\JsonResponse;
use Crm\ApiModule\Authorization\ApiAuthorizationInterface;
use Crm\SubscriptionsModule\Repository\ContentAccessRepository;
use Nette\Http\Response;

class ListContentAccessHandler extends ApiHandler
{
    private $contentAccessRepository;

    public function __construct(ContentAccessRepository $contentAccessRepository)
    {
        $this->contentAccessRepository = $contentAccessRepository;
    }

    public function params()
    {
        return [];
    }

    public function handle(ApiAuthorizationInterface $authorization)
    {
        $contentAccesses = $this->contentAccessRepository->all();

        $result = [];
        foreach ($contentAccesses as $contentAccess) {
            $result[] = [
                'code' => $contentAccess->name, // this is intentional code-name difference so we don't have to change API after column refactoring
                'description' => $contentAccess->description,
            ];
        }

        $response = new JsonResponse($result);
        $response->setHttpCode(Response::S200_OK);
        return $response;
    }
}
