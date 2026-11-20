<?php

declare(strict_types=1);

namespace App\Service;

use App\Dto\RegistryListQuery;
use App\Dto\RentalObjectInput;
use App\Dto\RentalObjectMeterSettingsInput;
use App\Entity\RentalObject;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

final readonly class RentalObjectService
{
    private const PAGE_SIZE = 20;

    public function __construct(
        private EntityManagerInterface $em,
        private LandLordService $landLords,
        private ContractService $contracts,
    ) {
    }

    public function list(RegistryListQuery $input): array
    {
        $query = $this->em->getRepository(RentalObject::class)->createQueryBuilder('r')
            ->addSelect('l')->join('r.landLord', 'l');
        if ('' !== trim($input->query)) {
            $query->andWhere('LOWER(r.name) LIKE :search OR LOWER(r.address) LIKE :search OR LOWER(l.name) LIKE :search')
                ->setParameter('search', '%'.mb_strtolower(trim($input->query)).'%');
        }

        $total = (int) (clone $query)->select('COUNT(r.id)')->getQuery()->getSingleScalarResult();
        $records = $query->orderBy('r.name', 'ASC')->addOrderBy('r.id', 'ASC')
            ->setFirstResult(($input->page - 1) * self::PAGE_SIZE)->setMaxResults(self::PAGE_SIZE)->getQuery()->getResult();

        return ['items' => array_map($this->present(...), $records), 'total' => $total, 'page' => $input->page, 'pageSize' => self::PAGE_SIZE];
    }

    public function present(RentalObject $record): array
    {
        return [
            'id' => $record->getId(), 'name' => $record->getName(),
            'address' => $record->getAddress(), 'square' => $record->getSquare(),
            'landLord' => $this->landLords->present($record->getLandLord()),
            'readingDay' => $record->getReadingDay(),
        ];
    }

    public function save(RentalObjectInput $input, ?RentalObject $record = null): RentalObject
    {
        $landLord = $this->landLords->find($input->landLordId);
        if (null === $landLord) {
            throw new UnprocessableEntityHttpException('Выбранный арендодатель не найден. Обновите список и выберите другого.');
        }

        if (null !== $record && $record->getLandLord()->getId() !== $landLord->getId() && $this->contracts->hasUnfinishedForObject($record)) {
            throw new ConflictHttpException('Смена арендодателя требует перезаключения договоров. Сначала завершите текущие и будущие договоры объекта.');
        }

        $record ??= new RentalObject($landLord, $input->square, trim($input->name), trim($input->address), $input->readingDay);
        $record->setName(trim($input->name));
        $record->setAddress(trim($input->address));
        $record->setSquare($input->square);
        $record->setLandLord($landLord);
        $record->setReadingDay($input->readingDay);
        $this->em->persist($record);
        $this->em->flush();

        return $record;
    }

    public function updateMeterSettings(RentalObject $object, RentalObjectMeterSettingsInput $input): RentalObject
    {
        $object->setReadingDay($input->readingDay);
        $this->em->flush();

        return $object;
    }
}
