<?php

declare(strict_types=1);

namespace App\Service;

use App\Dto\MeterInstallationInput;
use App\Entity\Meter;
use App\Entity\RentalObject;
use App\Entity\Space;
use App\Exception\InputException;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;

final readonly class MeterInstallationService
{
    public function __construct(private EntityManagerInterface $em, private ObjectMeterService $objects, private SpaceMeterService $spaces, private ObjectUnityProviderService $providers, private UnityProviderContractMeterService $contracts)
    {
    }

    public function present(Meter $meter): ?array
    {
        $object = $this->objects->find($meter);
        if (null !== $object) {
            return $this->objects->present($object);
        }
        $space = $this->spaces->find($meter);

        return null === $space || $space->getSpace()->isDeleted() ? null : $this->spaces->present($space);
    }

    public function save(Meter $meter, MeterInstallationInput $input): void
    {
        if (null !== $input->objectId && null !== $input->spaceId) {
            throw new InputException('spaceId', 'Прибор устанавливается только в одном месте.');
        }
        $object = null === $input->objectId ? null : ($this->em->getRepository(RentalObject::class)->find($input->objectId) ?? throw new InputException('objectId', 'Объект не найден.'));
        $space = null === $input->spaceId ? null : ($this->em->getRepository(Space::class)->find($input->spaceId) ?? throw new InputException('spaceId', 'Помещение не найдено.'));

        $this->em->wrapInTransaction(function () use ($meter, $input, $object, $space): void {
            $this->em->refresh($meter, LockMode::PESSIMISTIC_WRITE);
            if (null !== $input->utilityId && $input->utilityId !== $meter->getUtility()->getId()) {
                throw new InputException('meterId', 'Выберите счётчик указанной коммуникации.');
            }
            if ($input->requireUninstalled && null !== $this->present($meter)) {
                throw new InputException('meterId', 'Счётчик уже установлен. Выберите свободный прибор.');
            }
            $provider = null;

            if (null !== $input->objectUnityProviderId) {
                $provider = $this->providers->find($input->objectUnityProviderId);

                if (null === $object || null === $provider || $provider->getObject()->getId() !== $object->getId() || $provider->getUnity()->getId() !== $meter->getUtility()->getId()) {
                    throw new InputException('objectUnityProviderId', 'Выберите поставщика этого объекта и коммуникации. Для помещения поставщик не задаётся.');
                }
            }

            $previous = $this->present($meter);
            $sameObject = null !== $object && 'object' === ($previous['type'] ?? null) && $previous['id'] === $object->getId() && ($previous['provider']['id'] ?? null) === $input->objectUnityProviderId;
            $sameSpace = null !== $space && 'space' === ($previous['type'] ?? null) && $previous['id'] === $space->getId();
            if (null !== $previous && !$sameObject && !$sameSpace) {
                $this->contracts->release($meter);
            }

            $comment = null === $input->comment || '' === trim($input->comment) ? null : trim($input->comment);
            if (null === $object) {
                $this->objects->clear($meter);
            }
            if (null === $space) {
                $this->spaces->clear($meter);
            }
            if (null !== $object) {
                $this->objects->save($meter, $object, $comment, $provider);
            }
            if (null !== $space) {
                $this->spaces->save($meter, $space, $comment);
            }
        });
    }
}
