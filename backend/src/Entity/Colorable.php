<?php

declare(strict_types=1);

namespace App\Entity;

/** A stable discriminator and persisted ID for the polymorphic display colour. */
interface Colorable
{
    public static function colorType(): string;

    public function getId(): ?int;
}
