<?php

declare(strict_types=1);

namespace App\Security;

enum Role: string
{
    case Admin = 'ROLE_ADMIN';
    case SuperAdmin = 'ROLE_SUPER_ADMIN';
    case Accountant = 'ROLE_ACCOUNTANT';
    case Technician = 'ROLE_TECHNICIAN';
    case Lawyer = 'ROLE_LAWYER';
    case Director = 'ROLE_DIRECTOR';

    /** @return array<string, string> */
    public static function labels(): array
    {
        return [
            self::Admin->value => 'Администратор',
            self::SuperAdmin->value => 'Суперадмин',
            self::Accountant->value => 'Бухгалтерия',
            self::Technician->value => 'Технарь',
            self::Lawyer->value => 'Юрист',
            self::Director->value => 'Директор',
        ];
    }

    public function description(): string
    {
        return match ($this) {
            self::Admin => 'Управление своей учётной записью и обычными пользователями.',
            self::SuperAdmin => 'Системная учётная запись: управление всеми пользователями и администраторами.',
            self::Accountant => 'Бухгалтерский учёт, платежи и взаиморасчёты.',
            self::Technician => 'Внесение и исправление показаний; создание, установка, снятие и перенос счётчиков.',
            self::Lawyer => 'Оформление и сопровождение договоров аренды.',
            self::Director => 'Просмотр показателей и аналитики.',
        };
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_keys(self::labels());
    }
}
