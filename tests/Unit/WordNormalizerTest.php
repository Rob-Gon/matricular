<?php

namespace Tests\Unit;

use App\Support\WordNormalizer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class WordNormalizerTest extends TestCase
{
    /**
     * @return array<string, array{string, string}>
     */
    public static function words(): array
    {
        return [
            'plain lowercase' => ['barco', 'BARCO'],
            'accent' => ['camión', 'CAMION'],
            'uppercase accent' => ['CAMIÓN', 'CAMION'],
            'mixed case' => ['Camión', 'CAMION'],
            'diaeresis' => ['pingüino', 'PINGUINO'],
            'enye lowercase' => ['niño', 'NIÑO'],
            'enye uppercase' => ['NIÑO', 'NIÑO'],
            'enye with accent' => ['añadí', 'AÑADI'],
            'surrounding spaces' => ['  árbol ', 'ARBOL'],
        ];
    }

    #[DataProvider('words')]
    public function test_key(string $input, string $expected): void
    {
        $this->assertSame($expected, WordNormalizer::key($input));
    }

    public function test_enye_is_not_the_same_letter_as_n(): void
    {
        $this->assertNotSame(WordNormalizer::key('nino'), WordNormalizer::key('niño'));
    }
}
