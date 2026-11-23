<?php

namespace Tests\Unit;

use App\Sincronizacion\Documento;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class DocumentoTest extends TestCase
{
    /**
     * @return array<string, array{?string, ?string}>
     */
    public static function documentosDePersona(): array
    {
        return [
            'DNI with spaces and lowercase' => ['1234567-8 z', '12345678Z'],
            'DNI missing its leading zero' => ['1234567L', '01234567L'],
            'NIE with dashes' => ['x-1234567-l', 'X1234567L'],
            'NIE missing a leading zero' => ['Y123456Z', 'Y0123456Z'],
            'passport' => ['PA1234567', 'PA1234567'],
            'empty' => ['  ', null],
            'null' => [null, null],
            'only zeros' => ['00000000', null],
            'too short to be safe' => ['A123', null],
            'letters only' => ['NOTIENE', null],
        ];
    }

    #[DataProvider('documentosDePersona')]
    public function test_person_documents_are_normalized(?string $valor, ?string $esperado): void
    {
        $this->assertSame($esperado, Documento::persona($valor));
    }

    public function test_company_cif_is_normalized_and_self_employed_nif_is_accepted(): void
    {
        $this->assertSame('B95123456', Documento::empresa('b-95.123.456'));
        $this->assertSame('01234567L', Documento::empresa('1234567L'));
        $this->assertNull(Documento::empresa(''));
    }
}
