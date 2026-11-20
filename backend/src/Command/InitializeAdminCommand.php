<?php

declare(strict_types=1);

namespace App\Command;

use App\Dto\PasswordInput;
use App\Dto\UserInput;
use App\Entity\User;
use App\Repository\UserRepository;
use App\Security\Role;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[AsCommand(name: 'app:user:initialize-admin', description: 'Задать начальный пароль системной учётной записи, созданной миграцией.')]
final readonly class InitializeAdminCommand
{
    public function __construct(private UserRepository $users, private ValidatorInterface $validator, private UserPasswordHasherInterface $hasher, private EntityManagerInterface $em)
    {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Argument(description: 'Имя администратора')]
        string $firstName,
        #[Option(description: 'Прочитать пароль из stdin')]
        bool $passwordStdin = false
    ): int {
        $user = array_find($this->users->findAll(), static fn (User $candidate): bool => $candidate->isSuperAdmin());
        if (null === $user || $user->isActive() || '' !== $user->getPassword()) {
            $io->error('Системная учётная запись отсутствует или уже инициализирована. Сначала выполните миграции.');

            return Command::FAILURE;
        }
        $password = $passwordStdin ? rtrim(stream_get_contents(STDIN), "\r\n") : $io->askHidden('Пароль администратора');
        if (!is_string($password)) {
            $io->error('Пароль не задан.');

            return Command::FAILURE;
        }
        $input = new UserInput($user->getEmail(), [Role::Admin->value, Role::SuperAdmin->value], firstName: $firstName);
        $errors = $this->validator->validate($input);
        if (count($this->validator->validate(new PasswordInput($password))) > 0 || count($errors) > 0) {
            $io->error('Проверьте email и пароль длиной от '.PasswordInput::MIN_LENGTH.' символов.');

            return Command::FAILURE;
        }
        $user->setFirstName($input->firstName);
        $user->setPassword($this->hasher->hashPassword($user, $password));
        $user->setActive(true);
        $this->em->flush();
        $io->success('Системная учётная запись инициализирована: '.$user->getEmail());

        return Command::SUCCESS;
    }
}
