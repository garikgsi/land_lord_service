<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Contract;
use App\Entity\RentalObject;
use Doctrine\ORM\EntityManagerInterface;

final readonly class ContractService
{
    public function __construct(private EntityManagerInterface $em)
    {
    }

    public function hasUnfinishedForObject(RentalObject $object): bool
    {
        return (int) $this->em->getRepository(Contract::class)->createQueryBuilder('c')
            ->select('COUNT(c.id)')->join('c.space', 's')
            ->where('s.object = :object AND c.expiryDate >= :today')
            ->andWhere('c.annulmentDate IS NULL OR c.annulmentDate >= :today')
            ->andWhere('c.annulmentDate IS NULL OR c.annulmentDate >= c.contractDate')
            ->setParameter('object', $object)->setParameter('today', new \DateTimeImmutable('today'))
            ->getQuery()->getSingleScalarResult() > 0;
    }
}
