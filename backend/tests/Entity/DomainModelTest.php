<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\Contract;
use App\Entity\ContractAccrual;
use App\Entity\ContractAccrualCorrection;
use App\Entity\ContractFile;
use App\Entity\ContractPayment;
use App\Entity\ContractType;
use App\Entity\LandLord;
use App\Entity\Meter;
use App\Entity\MeterReading;
use App\Entity\MeterVerification;
use App\Entity\ObjectMeter;
use App\Entity\ObjectUnityProvider;
use App\Entity\Ownership;
use App\Entity\RentalObject;
use App\Entity\Renter;
use App\Entity\RenterContact;
use App\Entity\Space;
use App\Entity\SpaceMeter;
use App\Entity\Unity;
use App\Entity\UnityProvider;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class DomainModelTest extends KernelTestCase
{
    public function testLinkedModelsRoundTripWithExactDecimalsAndImmutableDates(): void
    {
        self::bootKernel();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $connection = $em->getConnection();
        if (!str_ends_with($connection->getDatabase(), '_test')) {
            throw new \LogicException('A separate test database is required.');
        }
        $connection->beginTransaction();
        try {
            $author = new User('orm-roundtrip@test.example');
            $landLord = new LandLord('Арендодатель');
            $object = new RentalObject($landLord, '200.12345678901234567890123456789', 'Объект', 'Адрес');
            $space = new Space($object, 'Space', '100.01');
            $renter = new Renter($em->find(Ownership::class, 1), 'Арендатор');
            $start = new \DateTimeImmutable('2026-11-01');
            $end = new \DateTimeImmutable('2026-11-30');
            $contract = new Contract($space, $renter, $landLord, $em->find(ContractType::class, 1), '0', $end, $start, 'N1', '10000.12345678901234567890123456789');
            $unity = new Unity('Вода', true);
            $provider = new UnityProvider($unity, 'Поставщик');
            $meter = new Meter($unity, $start, 100, 365);
            $spaceMeter = new Meter($unity, $start, 0, 365);
            $reading = new MeterReading($meter, new \DateTimeImmutable('2026-11-20 12:00:00'), 150);
            $accrual = new ContractAccrual($contract, $start, $start, $end, $contract->getMonthlyPayment(), '10000.12');
            $correction = new ContractAccrualCorrection($accrual, $start, $end, $contract->getMonthlyPayment(), '0', $author);
            $entities = [
                $author, $landLord, $object, $space, $renter, $contract, $unity,
                $provider, $meter, $spaceMeter, $reading, $accrual, $correction,
                new RenterContact($renter, 'Имя'),
                new ObjectUnityProvider($provider, $object),
                new ObjectMeter($object, $meter),
                new SpaceMeter($space, $spaceMeter),
                new MeterVerification($meter, $start),
                new ContractFile($contract, 'test.pdf', bin2hex(random_bytes(16)), 'application/pdf', 12),
                new ContractPayment($contract, $author, $start, '100.001'),
            ];
            foreach ($entities as $entity) {
                $em->persist($entity);
            }
            $em->flush();
            $id = $correction->getId();
            $em->clear();
            $stored = $em->find(ContractAccrualCorrection::class, $id);
            $storedContract = $stored->getContractAccrual()->getContract();

            self::assertSame(['0', '10000.12345678901234567890123456789', '200.12345678901234567890123456789', 'Арендатор', '2026-11-01', 25], [
                $stored->getAmount(), $storedContract->getMonthlyPayment(),
                $storedContract->getSpace()->getObject()->getSquare(),
                $storedContract->getRenter()->getName(),
                $storedContract->getContractDate()->format('Y-m-d'),
                $storedContract->getSpace()->getObject()->getReadingDay(),
            ]);
        } finally {
            $connection->rollBack();
        }
    }
}
