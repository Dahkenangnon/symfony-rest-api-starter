<?php

namespace App\Factory;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\SerializerInterface;

class JsonResponseFactory
{
    public function __construct(private SerializerInterface $serializer)
    {
    }

    public function create(object $data, int $status = 200, array $headers =  ['Content-Type' => 'application/json']): JsonResponse
    {
        return new JsonResponse(
            $this->serializer->serialize($data, JsonEncoder::FORMAT),
            $status,
            $headers,
            true
        );
    }
}