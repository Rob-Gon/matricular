<?php

namespace Tests\Unit;

use App\Dictionary\HunspellExpander;
use App\Dictionary\RlaEsSource;
use PHPUnit\Framework\TestCase;

class HunspellExpanderTest extends TestCase
{
    private function expander(): HunspellExpander
    {
        return new HunspellExpander((string) file_get_contents(__DIR__.'/../Fixtures/dictionary/es_ES.aff'));
    }

    /**
     * @param  list<string>  $expected
     * @param  list<string>  $actual
     */
    private function assertSameForms(array $expected, array $actual): void
    {
        sort($expected);
        sort($actual);
        $this->assertSame($expected, $actual);
    }

    public function test_suffix_conditions_pick_the_right_rule(): void
    {
        $this->assertSameForms(['barco', 'barcos'], $this->expander()->expand('barco', ['S']));
        $this->assertSameForms(['útil', 'útiles'], $this->expander()->expand('útil', ['S']));
    }

    public function test_continuation_flags_apply_to_derived_forms(): void
    {
        $this->assertSameForms(['bonito', 'bonitos', 'bonita', 'bonitas'], $this->expander()->expand('bonito', ['G', 'S']));
    }

    public function test_prefixes_combine_with_cross_product_suffixes(): void
    {
        $this->assertSameForms(['útil', 'útiles', 'inútil', 'inútiles'], $this->expander()->expand('útil', ['I', 'S']));
    }

    public function test_excluded_forms_are_skipped(): void
    {
        $forms = $this->expander()->excluding(RlaEsSource::isEnclitic(...))->expand('comer', ['À', 'X']);

        $this->assertSameForms(['comer', 'comiendo'], $forms);
    }

    public function test_dic_lines_are_split_into_stem_and_flags(): void
    {
        $this->assertSame(['bonito', ['G', 'S']], HunspellExpander::parseDicLine("bonito/GS\n"));
        $this->assertSame(['árbol', ['S']], HunspellExpander::parseDicLine('árbol/S po:noun'));
        $this->assertSame(['ir', []], HunspellExpander::parseDicLine('ir'));
    }
}
