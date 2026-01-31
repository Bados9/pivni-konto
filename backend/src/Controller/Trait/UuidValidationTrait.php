<?php

namespace App\Controller\Trait;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Uid\Uuid;

trait UuidValidationTrait
{
    private function parseUuid(string $id): ?Uuid
    {
        if (!Uuid::isValid($id)) {
            return null;
        }

        return Uuid::fromString($id);
    }

    private function invalidUuidResponse(): JsonResponse
    {
        return new JsonResponse(
            ['error' => 'Neplatný formát identifikátoru'],
            Response::HTTP_BAD_REQUEST
        );
    }
}
