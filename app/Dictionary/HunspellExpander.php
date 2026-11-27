<?php

namespace App\Dictionary;

use InvalidArgumentException;

/**
 * Generates every word form a Hunspell dictionary entry stands for (like Hunspell's `unmunch`).
 *
 * Supports what Spanish RLA-ES uses: UTF-8 single-character flags, prefixes and suffixes with
 * cross products, and one level of continuation flags on suffixes (e.g. -ción + plural).
 * Not supported (not used by RLA-ES): compounding, NEEDAFFIX, CIRCUMFIX, long/numeric flags.
 *
 * @phpstan-type AffixRule array{strip: string, add: string, continuation: list<string>, condition: string}
 * @phpstan-type AffixClass array{type: 'PFX'|'SFX', cross: bool, rules: list<AffixRule>}
 */
class HunspellExpander
{
    /** @var array<string, AffixClass> */
    private array $affixes = [];

    /** @var (callable(string, string): bool)|null */
    private $exclude = null;

    public function __construct(string $affContent)
    {
        $this->parse($affContent);
    }

    /**
     * Skips the forms the callback rejects (and anything derived from them).
     *
     * @param  callable(string $flag, string $form): bool  $exclude
     */
    public function excluding(callable $exclude): static
    {
        $this->exclude = $exclude;

        return $this;
    }

    /**
     * Splits a .dic line ("casa/S" or "árbol/S po:noun") into stem and flags.
     *
     * @return array{0: string, 1: list<string>}
     */
    public static function parseDicLine(string $line): array
    {
        $line = preg_split('/\s/u', trim($line), 2)[0] ?? '';
        $parts = explode('/', $line, 2);

        return [$parts[0], isset($parts[1]) ? mb_str_split($parts[1]) : []];
    }

    /**
     * @param  list<string>  $flags
     * @return list<string> The stem and every derived form, without duplicates.
     */
    public function expand(string $stem, array $flags): array
    {
        $forms = [$stem => true];
        /** @var list<string> $crossSuffixed */
        $crossSuffixed = [];

        foreach ($flags as $flag) {
            $class = $this->affixes[$flag] ?? null;
            if ($class === null || $class['type'] !== 'SFX') {
                continue;
            }

            foreach ($this->apply($flag, $class, $stem) as [$form, $rule]) {
                $forms[$form] = true;
                if ($class['cross']) {
                    $crossSuffixed[] = $form;
                }

                foreach ($rule['continuation'] as $continuationFlag) {
                    $continuation = $this->affixes[$continuationFlag] ?? null;
                    if ($continuation === null || $continuation['type'] !== 'SFX') {
                        continue;
                    }
                    foreach ($this->apply($continuationFlag, $continuation, $form) as [$derived]) {
                        $forms[$derived] = true;
                    }
                }
            }
        }

        foreach ($flags as $flag) {
            $class = $this->affixes[$flag] ?? null;
            if ($class === null || $class['type'] !== 'PFX') {
                continue;
            }

            $bases = $class['cross'] ? [$stem, ...$crossSuffixed] : [$stem];
            foreach ($bases as $base) {
                foreach ($this->apply($flag, $class, $base) as [$form]) {
                    $forms[$form] = true;
                }
            }
        }

        return array_map('strval', array_keys($forms));
    }

    /**
     * @param  AffixClass  $class
     * @return list<array{0: string, 1: AffixRule}>
     */
    private function apply(string $flag, array $class, string $word): array
    {
        $results = [];
        $isSuffix = $class['type'] === 'SFX';

        foreach ($class['rules'] as $rule) {
            if (! preg_match($rule['condition'], $word)) {
                continue;
            }

            if ($isSuffix) {
                if ($rule['strip'] !== '' && ! str_ends_with($word, $rule['strip'])) {
                    continue;
                }
                $form = mb_substr($word, 0, mb_strlen($word) - mb_strlen($rule['strip'])).$rule['add'];
            } else {
                if ($rule['strip'] !== '' && ! str_starts_with($word, $rule['strip'])) {
                    continue;
                }
                $form = $rule['add'].mb_substr($word, mb_strlen($rule['strip']));
            }

            if ($this->exclude !== null && ($this->exclude)($flag, $form)) {
                continue;
            }

            $results[] = [$form, $rule];
        }

        return $results;
    }

    private function parse(string $content): void
    {
        $remaining = [];

        foreach (preg_split('/\R/u', $content) ?: [] as $line) {
            $fields = preg_split('/\s+/u', trim($line)) ?: [];
            $type = $fields[0] ?? '';

            if ($type !== 'PFX' && $type !== 'SFX') {
                continue;
            }

            $flag = $fields[1] ?? '';

            // Header: "SFX S Y 4" (type, flag, cross product, rule count).
            if (! isset($remaining[$flag]) || $remaining[$flag] === 0) {
                if (count($fields) < 4 || ! ctype_digit($fields[3])) {
                    throw new InvalidArgumentException("Malformed affix header: {$line}");
                }
                $this->affixes[$flag] = ['type' => $type, 'cross' => $fields[2] === 'Y', 'rules' => []];
                $remaining[$flag] = (int) $fields[3];

                continue;
            }

            // Rule: "SFX S 0 es [^aeiou]" (type, flag, strip, add[/continuation], condition).
            if (count($fields) < 5) {
                throw new InvalidArgumentException("Malformed affix rule: {$line}");
            }

            [$add, $continuation] = array_pad(explode('/', $fields[3], 2), 2, '');
            $condition = $fields[4] === '.' ? '' : $fields[4];

            $this->affixes[$flag]['rules'][] = [
                'strip' => $fields[2] === '0' ? '' : $fields[2],
                'add' => $add === '0' ? '' : $add,
                'continuation' => $continuation === '' ? [] : mb_str_split($continuation),
                'condition' => $type === 'SFX' ? "/{$condition}$/u" : "/^{$condition}/u",
            ];
            $remaining[$flag]--;
        }
    }
}
