<?php

declare(strict_types=1);

namespace App\Doctrine;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\DecimalType;

/** Project NUMERIC columns deliberately have no precision or scale limit. */
final class UnboundedNumericType extends DecimalType
{
    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return 'NUMERIC';
    }
}
