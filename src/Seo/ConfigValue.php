<?php

declare(strict_types=1);

namespace Appolo\BoltSeo\Seo;

/**
 * Narrows values read from the extension's YAML config, or from the JSON stored
 * in a record's `seo` field, to the types the code works with. Nothing validates
 * either source, so a value can be of any type: a wrong one is treated as unset
 * instead of causing a TypeError on the page.
 *
 * Every value is read the same way:
 *  - text is used as is, including `'0'`;
 *  - a number (`title: 2024`, `keywords: 123`) is used as text;
 *  - `true`, `false`, a list or a map counts as not set, like an empty string,
 *    so the next source in the fallback chain is used.
 *
 * Before these rules, override and `default` values were checked with empty(),
 * which also skipped `'0'` and `0`; a number reached the string-typed helpers as
 * an int, and a list or `true` was either printed as `Array`/`1` or caused a
 * TypeError, depending on the getter.
 */
final class ConfigValue
{
    /**
     * Strings pass through. YAML numbers (`title: 2024`) are kept as text.
     * Anything else (arrays, booleans, null) is null.
     */
    public static function string(mixed $value): ?string
    {
        if (is_string($value)) {
            return $value;
        }

        return is_int($value) || is_float($value) ? (string) $value : null;
    }

    /**
     * @return array<mixed>
     */
    public static function array(mixed $value): array
    {
        return is_array($value) ? $value : [];
    }

    /**
     * The content field names configured under `fields.<name>`, e.g.
     * `title: ['title', 'name']`. A single name (`title: title`) is accepted too.
     * Entries that are not text are skipped, and anything other than a list or a
     * name configures no fields. Both used to be a TypeError.
     *
     * @return array<int, string>
     */
    public static function fieldNames(mixed $fieldsConfig, string $name): array
    {
        $names = self::array($fieldsConfig)[$name] ?? null;
        if (is_string($names)) {
            return [$names];
        }

        return array_values(array_filter(self::array($names), is_string(...)));
    }
}
