<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Beer;
use App\Repository\BeerRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\RateLimiter\RateLimiterFactory;

class BeerSubmissionProcessor implements ProcessorInterface
{
    public function __construct(
        private ProcessorInterface $persistProcessor,
        private Security $security,
        private BeerRepository $beerRepository,
        private RateLimiterFactory $beerSuggestionLimiter,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        if (!$data instanceof Beer) {
            return $this->persistProcessor->process($data, $operation, $uriVariables, $context);
        }

        $user = $this->security->getUser();

        // Rate limit
        $limiter = $this->beerSuggestionLimiter->create($user->getId()->toRfc4122());
        if (!$limiter->consume()->isAccepted()) {
            throw new TooManyRequestsHttpException(null, 'Příliš mnoho návrhů piv. Zkuste to později.');
        }

        // Trim name
        $data->setName(trim($data->getName()));

        // Check duplicate (case-insensitive)
        $existing = $this->beerRepository->findByNameCaseInsensitive($data->getName());
        if ($existing !== null) {
            throw new UnprocessableEntityHttpException('Pivo s tímto názvem již existuje.');
        }

        // Check pending limit
        $pendingCount = $this->beerRepository->countPendingByUser($user);
        if ($pendingCount >= 10) {
            throw new UnprocessableEntityHttpException('Máte příliš mnoho neschválených návrhů (max 10).');
        }

        $data->setStatus('pending');
        $data->setSubmittedBy($user);

        return $this->persistProcessor->process($data, $operation, $uriVariables, $context);
    }
}
