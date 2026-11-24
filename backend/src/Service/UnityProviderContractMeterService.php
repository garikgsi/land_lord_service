<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Meter;
use App\Entity\UnityProviderContract;
use Doctrine\ORM\EntityManagerInterface;

final readonly class UnityProviderContractMeterService
{
    public function __construct(private EntityManagerInterface $em)
    {
    }

    public function release(Meter $meter): void
    {
        foreach ($this->em->getRepository(UnityProviderContract::class)->findBy(['meter' => $meter]) as $contract) {
            $contract->setMeter(null);
        }
    }
}
