<?php

declare(strict_types=1);

namespace Appolo\BoltSeo\Tests\Seo;

use Appolo\BoltSeo\Seo\Seo;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\Request;
use Twig\Environment;

/**
 * Nothing validates the YAML config or the JSON in a record's `seo` field, so a
 * value can be of any type. These tests pin how each type is read (see
 * ConfigValue): text as is, including '0'; numbers as text; `true`, `false`,
 * lists and maps as not set. Before, depending on the getter, such values were
 * skipped by empty() ('0'), printed as `1`/`Array`, or caused a TypeError.
 */
class ConfigValueTypesTest extends SeoTestCase
{
    /**
     * What each getter returns when no source sets a value (see makeSeo()).
     */
    private const FALLBACKS = [
        'title' => 'My Site',
        'description' => 'Welcome',
        'keywords' => '',
        'ogtype' => 'website',
        'robots' => 'index, follow',
        'image' => '',
        'canonical' => 'http://localhost/',
    ];

    /**
     * @return iterable<string, array{string, mixed, ?string}>
     */
    public static function overrideAndDefaultProvider(): iterable
    {
        foreach (array_keys(self::FALLBACKS) as $getter) {
            yield from self::valueCases($getter);
        }
    }

    /**
     * The `seo` field stores every value except the image; og:type is stored as `og`.
     *
     * @return iterable<string, array{string, mixed, ?string}>
     */
    public static function seoFieldProvider(): iterable
    {
        foreach (array_keys(self::FALLBACKS) as $getter) {
            if ($getter !== 'image') {
                yield from self::valueCases($getter);
            }
        }
    }

    /**
     * @return iterable<string, array{string, mixed, ?string}>
     */
    private static function valueCases(string $getter): iterable
    {
        // null as the expected value means "falls back to the next source".
        yield "{$getter}: int" => [$getter, 2024, '2024'];
        yield "{$getter}: float" => [$getter, 1.5, '1.5'];
        yield "{$getter}: '0'" => [$getter, '0', '0'];
        yield "{$getter}: true" => [$getter, true, null];
        yield "{$getter}: false" => [$getter, false, null];
        yield "{$getter}: list" => [$getter, ['Fish and chips'], null];
        yield "{$getter}: map" => [$getter, ['en' => 'Fish and chips'], null];
    }

    #[DataProvider('overrideAndDefaultProvider')]
    public function testOverrideValue(string $getter, mixed $value, ?string $expected): void
    {
        $seo = $this->makeSeo('record', ['override_default' => ['record' => [$getter => $value]]]);

        self::assertSame($expected ?? self::FALLBACKS[$getter], $this->get($seo, $getter));
    }

    #[DataProvider('seoFieldProvider')]
    public function testSeoFieldValue(string $getter, mixed $value, ?string $expected): void
    {
        $key = $getter === 'ogtype' ? 'og' : $getter;
        $seo = $this->makeSeo('record', record: $this->recordWithSeoData([$key => $value]));

        self::assertSame($expected ?? self::FALLBACKS[$getter], $this->get($seo, $getter));
    }

    #[DataProvider('overrideAndDefaultProvider')]
    public function testDefaultValue(string $getter, mixed $value, ?string $expected): void
    {
        $seo = $this->makeSeo('record', ['default' => [$getter => $value]]);

        self::assertSame($expected ?? self::FALLBACKS[$getter], $this->get($seo, $getter));
    }

    // --- override entries ---------------------------------------------------

    /**
     * @return array<string, array{string, string, ?string}>
     */
    public static function overrideKeyProvider(): array
    {
        return [
            'route name' => ['record', 'record', null],
            'listing content type slug' => ['listing', 'blog', 'blog'],
            'base route of a localized route' => ['homepage_locale', 'homepage', null],
        ];
    }

    /**
     * An override entry that is not a map sets nothing. It used to be a TypeError.
     */
    #[DataProvider('overrideKeyProvider')]
    public function testOverrideEntryThatIsNotAMapIsIgnored(string $route, string $key, ?string $slug): void
    {
        $seo = $this->makeSeo($route, [
            'override_default' => [$key => 'Fish and chips'],
        ], contentTypeSlug: $slug, siteName: 'Acme');

        self::assertSame('Acme', $seo->title());
    }

    // --- request and Twig globals ------------------------------------------

    public function testRouteThatIsNotTextIsTreatedAsNoRoute(): void
    {
        $request = new Request(server: ['HTTP_HOST' => 'localhost']);
        $request->attributes->set('_route', 42);
        $seo = $this->makeSeo('', ['override_default' => ['42' => ['title' => 'Route 42']]], request: $request);

        self::assertSame('My Site', $seo->title());
    }

    /**
     * Unchanged: without a matched route, an override keyed '' applies, because
     * PHP has always looked up the null route as ''.
     */
    public function testWithoutARouteAnOverrideKeyedEmptyStringStillApplies(): void
    {
        $request = new Request(server: ['HTTP_HOST' => 'localhost']);
        $seo = $this->makeSeo('', ['override_default' => ['' => ['title' => 'No route']]], request: $request);

        self::assertSame('No route', $seo->title());
    }

    /**
     * A listing route without a content type slug has no content type to name the
     * page after. It used to pass null to Config::getContentType(): a TypeError.
     */
    public function testListingRouteWithoutContentTypeSlugFallsBackToSitename(): void
    {
        $seo = $this->makeSeo('listing', siteName: 'Acme');

        self::assertSame('Acme', $seo->title());
    }

    public function testRecordGlobalThatIsNotContentIsIgnored(): void
    {
        $twig = $this->createMock(Environment::class);
        $twig->method('getGlobals')->willReturn(['record' => 'Fish and chips']);

        $seo = $this->makeSeo('record', twig: $twig, siteName: 'Acme');

        self::assertSame('Acme', $seo->title());
    }

    // --- other config keys --------------------------------------------------

    /**
     * @return array<string, array{mixed}>
     */
    public static function nonTextProvider(): array
    {
        return [
            'true' => [true],
            'list' => [['Fish and chips']],
        ];
    }

    #[DataProvider('nonTextProvider')]
    public function testMetaTemplateThatIsNotTextKeepsTheDefaultTemplate(mixed $value): void
    {
        $captured = null;
        $twig = $this->createMock(Environment::class);
        $twig->method('getGlobals')->willReturn([]);
        $twig->method('render')->willReturnCallback(function (string $template) use (&$captured): string {
            $captured = $template;

            return '';
        });

        $this->makeSeo('record', ['templates' => ['meta' => $value]], twig: $twig)->metatags();

        self::assertSame('@seo/_metatags.html.twig', $captured);
    }

    /**
     * A separator that is not text is left out, like a missing `title_separator`.
     * It used to print `1` or `Array`.
     */
    #[DataProvider('nonTextProvider')]
    public function testTitleSeparatorThatIsNotTextIsLeftOut(mixed $value): void
    {
        $seo = $this->makeSeo('record', [
            'title_postfix' => 'Acme',
            'title_separator' => $value,
            'override_default' => ['record' => ['title' => 'Fish and chips']],
        ]);

        self::assertSame('Fish and chips Acme', $seo->title());
    }

    /**
     * A content type name that is not text counts as empty, as a missing name
     * always did. It used to print `Array`.
     */
    public function testContentTypeNameThatIsNotTextCountsAsEmpty(): void
    {
        $seo = $this->makeSeo('listing', contentTypeSlug: 'blog', contentType: $this->contentTypeNamed(['Blog']));

        self::assertSame('', $seo->title());
    }

    // --- Bolt's sitename and payoff -----------------------------------------

    /**
     * @return array<string, array{mixed, string}>
     */
    public static function boltValueProvider(): array
    {
        return [
            'int' => [2024, '2024'],
            'true' => [true, ''],
            'list' => [['Fish and chips'], ''],
        ];
    }

    /**
     * Read like this extension's config. A number used to be a TypeError.
     */
    #[DataProvider('boltValueProvider')]
    public function testSitenameAndPayoff(mixed $value, string $expected): void
    {
        $seo = $this->makeSeo('record', siteName: $value, payoff: $value);

        self::assertSame($expected, $seo->title());
        self::assertSame($expected, $seo->description());
    }

    // --- title postfix ------------------------------------------------------

    /**
     * A postfix that is not text means no postfix. `true` used to print `1`.
     */
    #[DataProvider('nonTextProvider')]
    public function testTitlePostfixThatIsNotTextMeansNoPostfix(mixed $value): void
    {
        $seo = $this->makeSeo('record', [
            'title_postfix' => $value,
            'override_default' => ['record' => ['title' => 'Fish and chips']],
        ]);

        self::assertSame('Fish and chips', $seo->title());
    }

    /**
     * An empty postfix falls back to the sitename, read like the postfix itself.
     */
    #[DataProvider('boltValueProvider')]
    public function testTitlePostfixFallsBackToSitename(mixed $siteName, string $expected): void
    {
        $seo = $this->makeSeo('record', [
            'title_postfix' => '',
            'override_default' => ['record' => ['title' => 'Fish and chips']],
        ], siteName: $siteName);

        self::assertSame($expected === '' ? 'Fish and chips' : "Fish and chips | {$expected}", $seo->title());
    }

    // --- record extras image ------------------------------------------------

    /**
     * @return array<string, array{mixed, string}>
     */
    public static function extrasImageProvider(): array
    {
        return [
            'url' => [['url' => '/files/fish.jpg'], '/files/fish.jpg'],
            'numeric url' => [['url' => 2024], '2024'],
            'no url' => [['alt' => 'Fish and chips'], ''],
            'url is not text' => [['url' => true], ''],
            'image is not a map' => ['/files/fish.jpg', ''],
        ];
    }

    /**
     * The last fallback for the image. A missing url or one that is not text used
     * to be a TypeError.
     */
    #[DataProvider('extrasImageProvider')]
    public function testRecordExtrasImage(mixed $image, string $expected): void
    {
        $seo = $this->makeSeo('record', record: $this->record([], ['image' => $image]));

        self::assertSame($expected, $seo->image());
    }

    // --- fields config ------------------------------------------------------

    public function testSingleFieldNameIsAccepted(): void
    {
        $seo = $this->makeSeo('record', [
            'fields' => ['title' => 'headline', 'description' => 'headline'],
        ], record: $this->record(['headline' => 'Fish and chips']));

        self::assertSame('Fish and chips', $seo->title());
        self::assertSame('Fish and chips', $seo->description());
    }

    public function testFieldNamesThatAreNotTextAreSkipped(): void
    {
        $seo = $this->makeSeo('record', [
            'fields' => ['title' => [5, null, 'headline']],
        ], record: $this->record(['headline' => 'Fish and chips']));

        self::assertSame('Fish and chips', $seo->title());
    }

    public function testFieldsEntryThatIsNeitherAListNorANameConfiguresNoFields(): void
    {
        $seo = $this->makeSeo('record', [
            'fields' => ['title' => true],
        ], record: $this->record(['title' => 'Fish and chips']));

        self::assertSame('My Site', $seo->title());
    }

    private function get(Seo $seo, string $getter): string
    {
        return match ($getter) {
            'title' => $seo->title(),
            'description' => $seo->description(),
            'keywords' => $seo->keywords(),
            'ogtype' => $seo->ogtype(),
            'robots' => $seo->robots(),
            'image' => $seo->image(),
            'canonical' => $seo->canonical(),
        };
    }

    private function contentTypeNamed(mixed $name): \Bolt\Configuration\Content\ContentType
    {
        $contentType = $this->createMock(\Bolt\Configuration\Content\ContentType::class);
        $contentType->method('get')->willReturn($name);

        return $contentType;
    }
}
