<?php

namespace Crm\SubscriptionsModule\Api\v1;

use Crm\ApiModule\Api\ApiHandler;
use Crm\SubscriptionsModule\Repository\ContentAccessRepository;
use Nette\Http\Response;
use Tomaj\NetteApi\Response\JsonApiResponse;
use Tomaj\NetteApi\Response\ResponseInterface;

class ListContentAccessHandler extends ApiHandler
{
    private $contentAccessRepository;

    public function __construct(ContentAccessRepository $contentAccessRepository)
    {
        $this->contentAccessRepository = $contentAccessRepository;
    }

    public function params(): array
    {
        return [];
    }

    public function handle(array $params): ResponseInterface
    {
        $contentAccesses = $this->contentAccessRepository->all();

        $result = [];
        foreach ($contentAccesses as $contentAccess) {
            $result[] = [
                'code' => $contentAccess->name, // this is intentional code-name difference so we don't have to change API after column refactoring
                'description' => $contentAccess->description,
            ];
        }

        $response = new JsonApiResponse(Response::S200_OK, $result);
        return $response;
    }
}
