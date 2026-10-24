<?php

declare(strict_types=1);

namespace Appolo\BoltSeo\Tests\Seo;

use Appolo\BoltSeo\Seo\ConfigValue;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Narrowing of untyped values from the YAML config and the `seo` field JSON.
 */
class ConfigValueTest extends TestCase
{
    /**
     * @return array<string, array{mixed, ?string}>
     */
    public static function stringProvider(): array
    {
        return [
            'string' => ['Fish and chips', 'Fish and chips'],
            'empty string' => ['', ''],
            'int' => [2024, '2024'],
            'float' => [1.5, '1.5'],
            'null' => [null, null],
            'bool' => [true, null],
            'array' => [['Fish and chips'], null],
        ];
    }

    #[DataProvider('stringProvider')]
    public function testString(mixed $value, ?string $expected): void
    {
        self::assertSame($expected, ConfigValue::string($value));
    }

    public function testArrayReturnsArraysAndEmptyArrayForAnythingElse(): void
    {
        self::assertSame(['a' => 1], ConfigValue::array(['a' => 1]));
        self::assertSame([], ConfigValue::array('a'));
        self::assertSame([], ConfigValue::array(null));
    }

    /**
     * @return array<string, array{mixed, array<int, string>}>
     */
    public static function fieldNamesProvider(): array
    {
        return [
            'list of names' => [['title' => ['title', 'name']], ['title', 'name']],
            'single name' => [['title' => 'headline'], ['headline']],
            'non-string entries dropped' => [['title' => ['title', 5, null, 'name']], ['title', 'name']],
            'not configured' => [['description' => ['body']], []],
            'empty list' => [['title' => []], []],
            'fields is not an array' => ['title', []],
            'fields missing' => [null, []],
        ];
    }

    /**
     * @param array<int, string> $expected
     */
    #[DataProvider('fieldNamesProvider')]
    public function testFieldNames(mixed $fieldsConfig, array $expected): void
    {
        self::assertSame($expected, ConfigValue::fieldNames($fieldsConfig, 'title'));
    }
}
