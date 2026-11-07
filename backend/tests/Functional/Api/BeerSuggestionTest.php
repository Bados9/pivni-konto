<?php

namespace App\Tests\Functional\Api;

use App\Entity\Beer;
use App\Entity\User;

class BeerSuggestionTest extends ApiTestCase
{
    public function testSuggestionCreatesPendingBeer(): void
    {
        $this->loginAs($this->createUser());

        $this->apiRequest('POST', '/api/beers/suggest', ['name' => '  Návrh ' . uniqid() . '  ']);

        $this->assertResponseStatusCodeSame(201);
        $this->assertSame('pending', $this->getResponseData()['status']);
    }

    public function testDuplicateOfApprovedBeerIsRejected(): void
    {
        $name = $this->createBeer('approved');
        $this->loginAs($this->createUser());

        $this->apiRequest('POST', '/api/beers/suggest', ['name' => mb_strtoupper($name)]);

        $this->assertResponseStatusCodeSame(422);
        $this->assertSame('Pivo s tímto názvem již existuje.', $this->getResponseData()['detail']);
    }

    public function testDuplicateOfSomeoneElsesPendingBeerExplainsWhy(): void
    {
        $name = $this->createBeer('pending', $this->createUser());
        $this->loginAs($this->createUser());

        $this->apiRequest('POST', '/api/beers/suggest', ['name' => $name]);

        $this->assertResponseStatusCodeSame(422);
        $this->assertSame('Toto pivo už někdo navrhl a čeká na schválení.', $this->getResponseData()['detail']);
    }

    public function testRejectedAttemptsDoNotUseUpRateLimit(): void
    {
        $name = $this->createBeer('approved');
        $this->loginAs($this->createUser());

        for ($i = 0; $i < 3; $i++) {
            $this->apiRequest('POST', '/api/beers/suggest', ['name' => $name]);
            $this->assertResponseStatusCodeSame(422);
        }

        $this->apiRequest('POST', '/api/beers/suggest', ['name' => 'Návrh ' . uniqid()]);
        $this->assertResponseStatusCodeSame(201);
    }

    private function createBeer(string $status, ?User $submittedBy = null): string
    {
        $beer = new Beer();
        $beer->setName('Pivo ' . uniqid());
        $beer->setStatus($status);
        $beer->setSubmittedBy($submittedBy);

        $this->entityManager->persist($beer);
        $this->entityManager->flush();

        return $beer->getName();
    }
}
