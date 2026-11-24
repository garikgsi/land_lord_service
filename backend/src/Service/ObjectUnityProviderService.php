<?php

declare(strict_types=1);

namespace App\Service;

use App\Dto\ObjectUnityProviderInput;
use App\Dto\ObjectUnityProviderListQuery;
use App\Entity\ObjectMeter;
use App\Entity\ObjectUnityProvider;
use App\Entity\RentalObject;
use App\Exception\InputException;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final readonly class ObjectUnityProviderService
{
    public function __construct(private EntityManagerInterface $em, private UnityProviderService $providers, private UnityService $unities)
    {
    }

    public function find(int $id): ?ObjectUnityProvider
    {
        return $this->em->getRepository(ObjectUnityProvider::class)->find($id);
    }

    public function present(ObjectUnityProvider $record): array
    {
        return [
            'id' => $record->getId(), 'name' => $record->getUnityProvider()->getName().' · '.$record->getUnity()->getName(),
            'objectId' => $record->getObject()->getId(),
            'unityProvider' => $this->providers->present($record->getUnityProvider()), 'unity' => $this->unities->present($record->getUnity()),
        ];
    }

    public function list(RentalObject $object, ObjectUnityProviderListQuery $input): array
    {
        $query = $this->em->getRepository(ObjectUnityProvider::class)->createQueryBuilder('op')
            ->join('op.unityProvider', 'p')->join('op.unity', 'u')->addSelect('p', 'u')
            ->andWhere('op.object = :object')->setParameter('object', $object);

        if (null !== $input->unityId) {
            $query->andWhere('u.id = :unity')->setParameter('unity', $input->unityId);
        }

        if ('' !== trim($input->query)) {
            $query->andWhere('LOWER(p.name) LIKE :query OR LOWER(u.name) LIKE :query')->setParameter('query', '%'.mb_strtolower(trim($input->query)).'%');
        }

        $items = array_map($this->present(...), $query->orderBy('op.id', 'DESC')->getQuery()->getResult());

        return ['items' => $items, 'total' => count($items), 'page' => 1, 'pageSize' => count($items)];
    }

    public function add(RentalObject $object, ObjectUnityProviderInput $input): ObjectUnityProvider
    {
        $provider = $this->providers->find($input->unityProviderId) ?? throw new InputException('unityProviderId', 'Поставщик не найден.');
        $unity = $this->unities->find($input->unityId) ?? throw new InputException('unityId', 'Коммуникация не найдена.');

        return $this->em->wrapInTransaction(function () use ($object, $provider, $unity): ObjectUnityProvider {
            $this->em->lock($object, LockMode::PESSIMISTIC_WRITE);
            $record = $this->em->getRepository(ObjectUnityProvider::class)->findOneBy(['object' => $object, 'unityProvider' => $provider, 'unity' => $unity])
                ?? new ObjectUnityProvider($provider, $object, $unity);
            $this->em->persist($record);
            $this->em->flush();

            return $record;
        });
    }

    public function remove(RentalObject $object, ObjectUnityProvider $record): void
    {
        if ($record->getObject()->getId() !== $object->getId()) {
            throw new NotFoundHttpException('Связь с поставщиком не найдена у этого объекта.');
        }

        if ($this->em->getRepository(ObjectMeter::class)->count(['objectUnityProvider' => $record]) > 0) {
            throw new ConflictHttpException('Поставщик выбран у счётчиков. Сначала измените или очистите их привязку.');
        }

        $this->em->remove($record);
        $this->em->flush();
    }
}
