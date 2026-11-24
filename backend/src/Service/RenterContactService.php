<?php

declare(strict_types=1);

namespace App\Service;

use App\Dto\RegistryListQuery;
use App\Dto\RenterContactInput;
use App\Entity\Renter;
use App\Entity\RenterContact;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final readonly class RenterContactService
{
    public function __construct(private EntityManagerInterface $em)
    {
    }

    public function requireRenter(RenterContact $contact, Renter $renter): void
    {
        if ($contact->getRenter()->getId() !== $renter->getId()) {
            throw new NotFoundHttpException('Контакт арендатора не найден.');
        }
    }

    public function present(RenterContact $record): array
    {
        return [
            'id' => $record->getId(), 'renterId' => $record->getRenter()->getId(),
            'name' => $record->getName(), 'lastName' => $record->getLastName(),
            'patronymic' => $record->getPatronymic(), 'phone' => $record->getPhone(), 'email' => $record->getEmail(),
        ];
    }

    public function list(Renter $renter, RegistryListQuery $input): array
    {
        $query = $this->em->getRepository(RenterContact::class)->createQueryBuilder('c')
            ->andWhere('c.renter = :renter')->setParameter('renter', $renter);
        if ('' !== trim($input->query)) {
            $query->andWhere("LOWER(CONCAT(c.name, ' ', COALESCE(c.lastName, ''), ' ', COALESCE(c.patronymic, ''), ' ', COALESCE(c.email, ''), ' ', COALESCE(c.phone, ''))) LIKE :query")
                ->setParameter('query', '%'.mb_strtolower(trim($input->query)).'%');
        }
        $total = (int) (clone $query)->select('COUNT(c.id)')->getQuery()->getSingleScalarResult();
        $records = $query->orderBy('c.lastName', 'ASC')->addOrderBy('c.name', 'ASC')->addOrderBy('c.id', 'DESC')
            ->setFirstResult(($input->page - 1) * 20)->setMaxResults(20)->getQuery()->getResult();

        return ['items' => array_map($this->present(...), $records), 'total' => $total, 'page' => $input->page, 'pageSize' => 20];
    }

    public function save(Renter $renter, RenterContactInput $input, ?RenterContact $record = null): RenterContact
    {
        if (null !== $record) {
            $this->requireRenter($record, $renter);
        }
        $record ??= new RenterContact($renter, trim($input->name));
        $record->setName(trim($input->name));
        $record->setLastName($this->optional($input->lastName));
        $record->setPatronymic($this->optional($input->patronymic));
        $record->setPhone(null === $this->optional($input->phone) ? null : '+'.preg_replace('/\D/', '', $input->phone));
        $record->setEmail(null === $this->optional($input->email) ? null : mb_strtolower(trim($input->email)));
        $this->em->persist($record);
        $this->em->flush();

        return $record;
    }

    private function optional(?string $value): ?string
    {
        return null === $value || '' === trim($value) ? null : trim($value);
    }
}
