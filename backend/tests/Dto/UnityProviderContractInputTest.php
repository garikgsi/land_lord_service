<?php

declare(strict_types=1);

namespace App\Tests\Dto;

use App\Dto\UnityProviderContractInput;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Validation;

final class UnityProviderContractInputTest extends TestCase
{
    #[DataProvider('utilitySelections')]
    public function testOneCommunicationAndOptionalMeter(array $unityIds, ?int $meterId, array $paths): void
    {
        $input = new UnityProviderContractInput(objectId: 1, unityProviderId: 2, expiryDate: '2027-12-31', unityIds: $unityIds, meterId: $meterId);
        $violations = Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator()->validate($input);
        $actual = [];
        foreach ($violations as $violation) {
            $actual[] = $violation->getPropertyPath();
        }

        self::assertSame($paths, $actual);
    }

    public static function utilitySelections(): iterable
    {
        yield 'without meter' => [[1], null, []];
        yield 'with meter' => [[1], 3, []];
        yield 'missing communication' => [[], null, ['unityIds']];
        yield 'multiple communications' => [[1, 2], null, ['unityIds']];
        yield 'invalid meter' => [[1], -1, ['meterId']];
    }
}
