<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Post;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\BeerEntry;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * State processor pro BeerEntry - zajišťuje, že user je vždy nastaven na aktuálně přihlášeného uživatele
 */
class BeerEntryProcessor implements ProcessorInterface
{
    public function __construct(
        private ProcessorInterface $persistProcessor,
        private Security $security,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        if ($data instanceof BeerEntry && $operation instanceof Post) {
            $user = $this->security->getUser();
            if ($user !== null) {
                $data->setUser($user);
            }
        }

        return $this->persistProcessor->process($data, $operation, $uriVariables, $context);
    }
}
