<?php

declare(strict_types=1);

namespace Appolo\BoltSeo\Tests\Seo;

use PHPUnit\Framework\Attributes\DataProvider;
use Twig\Environment;
use Twig\Markup;

/**
 * Fallback chains for the remaining meta getters (description, keywords, ogtype,
 * robots, image, canonical), the override "missing key" guard, and metatags().
 */
class SeoMetaTagsTest extends SeoTestCase
{
    // --- description -------------------------------------------------------

    public function testDescriptionUsesOverride(): void
    {
        $seo = $this->makeSeo('record', [
            'override_default' => [
                'record' => [
                    'description' => 'Override description',
                ],
            ],
        ]);

        self::assertSame('Override description', $seo->description());
    }

    public function testDescriptionUsesSeoField(): void
    {
        $record = $this->recordWithSeoData(['description' => 'Seo description']);
        $seo = $this->makeSeo('record', record: $record);

        self::assertSame('Seo description', $seo->description());
    }

    public function testDescriptionUsesRecordField(): void
    {
        $record = $this->record(['description' => 'Record description']);
        $seo = $this->makeSeo('record', record: $record);

        self::assertSame('Record description', $seo->description());
    }

    public function testSingletonOnListingRouteUsesSeoFieldDescription(): void
    {
        // A singleton served on the listing route must expose its own SEO
        // description, not fall back to the generic default.
        $record = $this->recordWithSeoData(['description' => 'Singleton seo description']);
        $seo = $this->makeSeo(
            'listing',
            contentTypeSlug: 'about',
            contentType: $this->contentType('About CT'),
            record: $record,
        );

        self::assertSame('Singleton seo description', $seo->description());
    }

    public function testSingletonOnListingRouteFallsBackToRecordDescriptionField(): void
    {
        // A singleton on the listing route without a SEO description falls back to
        // its own content field before the generic default.
        $record = $this->record(['description' => 'About record description']);
        $seo = $this->makeSeo(
            'listing',
            contentTypeSlug: 'about',
            contentType: $this->contentType('About CT'),
            record: $record,
            payoff: 'Welcome',
        );

        self::assertSame('About record description', $seo->description());
    }

    public function testDescriptionFallsBackToConfiguredDefault(): void
    {
        $seo = $this->makeSeo('record', [
            'default' => ['title' => '', 'description' => 'Default description', 'keywords' => ''],
        ]);

        self::assertSame('Default description', $seo->description());
    }

    public function testDescriptionFallsBackToPayoff(): void
    {
        $seo = $this->makeSeo('record', payoff: 'Our payoff');

        self::assertSame('Our payoff', $seo->description());
    }

    // --- keywords ----------------------------------------------------------

    public function testKeywordsUseOverride(): void
    {
        $seo = $this->makeSeo('record', [
            'override_default' => ['record' => ['keywords' => 'a, b, c']],
        ]);

        self::assertSame('a, b, c', $seo->keywords());
    }

    public function testKeywordsUseSeoField(): void
    {
        $record = $this->recordWithSeoData(['keywords' => 'seo, words']);
        $seo = $this->makeSeo('record', record: $record);

        self::assertSame('seo, words', $seo->keywords());
    }

    public function testKeywordsFallBackToConfiguredDefault(): void
    {
        $seo = $this->makeSeo('record', ['default' => ['title' => '', 'description' => '', 'keywords' => 'x, y']]);

        self::assertSame('x, y', $seo->keywords());
    }

    public function testKeywordsDefaultToEmptyStringWhenNoConfiguredDefault(): void
    {
        // No `keywords` key under `default` at all -> empty string.
        $seo = $this->makeSeo('record', ['default' => ['title' => '', 'description' => '']]);

        self::assertSame('', $seo->keywords());
    }

    /**
     * `keywords_length: 0` is the shipped default and means "keywords are disabled
     * in the editor" — it must not be handed to Html::trimText(), which treats 0 as
     * a real limit and chops the last character off before appending an ellipsis.
     *
     * @param array<string, mixed> $config
     */
    #[DataProvider('keywordsSourceProvider')]
    public function testKeywordsAreNotTruncatedWhenLengthIsZero(array $config, ?array $seoData): void
    {
        $record = $seoData === null ? null : $this->recordWithSeoData($seoData);
        $seo = $this->makeSeo('record', array_merge($config, ['keywords_length' => 0]), record: $record);

        self::assertSame('keyword one, keyword two', $seo->keywords());
    }

    /**
     * @return array<string, array{array<string, mixed>, ?array<string, mixed>}>
     */
    public static function keywordsSourceProvider(): array
    {
        return [
            'override' => [
                ['override_default' => ['record' => ['keywords' => 'keyword one, keyword two']]],
                null,
            ],
            'seo field' => [
                [],
                ['keywords' => 'keyword one, keyword two'],
            ],
            'configured default' => [
                ['default' => ['title' => '', 'description' => '', 'keywords' => 'keyword one, keyword two']],
                null,
            ],
        ];
    }

    public function testKeywordsAreStillTruncatedWhenLengthIsSet(): void
    {
        $seo = $this->makeSeo('record', [
            'keywords_length' => 12,
            'override_default' => ['record' => ['keywords' => 'keyword one, keyword two']],
        ]);

        self::assertSame('keyword one…', $seo->keywords());
    }

    // --- ogtype ------------------------------------------------------------

    public function testOgtypeUsesOverride(): void
    {
        $seo = $this->makeSeo('record', ['override_default' => ['record' => ['ogtype' => 'article']]]);

        self::assertSame('article', $seo->ogtype());
    }

    public function testOgtypeUsesSeoField(): void
    {
        $record = $this->recordWithSeoData(['og' => 'profile']);
        $seo = $this->makeSeo('record', record: $record);

        self::assertSame('profile', $seo->ogtype());
    }

    public function testOgtypeFallsBackToConfiguredDefault(): void
    {
        $seo = $this->makeSeo('record', [
            'default' => ['title' => '', 'description' => '', 'keywords' => '', 'ogtype' => 'article'],
        ]);

        self::assertSame('article', $seo->ogtype());
    }

    public function testOgtypeDefaultsToWebsite(): void
    {
        $seo = $this->makeSeo('record');

        self::assertSame('website', $seo->ogtype());
    }

    // --- robots ------------------------------------------------------------

    public function testRobotsUsesOverride(): void
    {
        $seo = $this->makeSeo('record', ['override_default' => ['record' => ['robots' => 'noindex, nofollow']]]);

        self::assertSame('noindex, nofollow', $seo->robots());
    }

    public function testRobotsUsesSeoField(): void
    {
        $record = $this->recordWithSeoData(['robots' => 'noindex, follow']);
        $seo = $this->makeSeo('record', record: $record);

        self::assertSame('noindex, follow', $seo->robots());
    }

    public function testRobotsFallsBackToConfiguredDefault(): void
    {
        $seo = $this->makeSeo('record', [
            'default' => ['title' => '', 'description' => '', 'keywords' => '', 'robots' => 'noindex, nofollow'],
        ]);

        self::assertSame('noindex, nofollow', $seo->robots());
    }

    public function testRobotsDefaultsToIndexFollow(): void
    {
        $seo = $this->makeSeo('record');

        self::assertSame('index, follow', $seo->robots());
    }

    // --- image -------------------------------------------------------------

    public function testImageUsesOverride(): void
    {
        $seo = $this->makeSeo('record', ['override_default' => ['record' => ['image' => 'https://cdn/x.jpg']]]);

        self::assertSame('https://cdn/x.jpg', $seo->image());
    }

    public function testImageUsesRecordFieldPrefixedWithHost(): void
    {
        $record = $this->record(['image' => '/media/cover.jpg']);
        $seo = $this->makeSeo('record', record: $record);

        self::assertSame('http://localhost/media/cover.jpg', $seo->image());
    }

    public function testImageFallsBackToConfiguredDefault(): void
    {
        $seo = $this->makeSeo('record', [
            'default' => ['title' => '', 'description' => '', 'keywords' => '', 'image' => '/default.jpg'],
        ]);

        self::assertSame('/default.jpg', $seo->image());
    }

    public function testImageFallsBackToRecordExtras(): void
    {
        $record = $this->record([], ['image' => ['url' => 'http://cdn/extra.jpg']]);
        $seo = $this->makeSeo('record', record: $record);

        self::assertSame('http://cdn/extra.jpg', $seo->image());
    }

    public function testImageUsesExtrasWhenImageFieldNotConfigured(): void
    {
        // `fields` has no `image` mapping -> getField bails early; extras still apply.
        $record = $this->record(['title' => 'x'], ['image' => ['url' => 'http://cdn/extra.jpg']]);
        $seo = $this->makeSeo('record', ['fields' => ['title' => ['title']]], record: $record);

        self::assertSame('http://cdn/extra.jpg', $seo->image());
    }

    public function testImageDefaultsToEmptyString(): void
    {
        $seo = $this->makeSeo('record');

        self::assertSame('', $seo->image());
    }

    // --- canonical ---------------------------------------------------------

    public function testCanonicalUsesOverride(): void
    {
        $seo = $this->makeSeo('record', ['override_default' => ['record' => ['canonical' => 'https://example.test/x']]]);

        self::assertSame('https://example.test/x', $seo->canonical());
    }

    public function testCanonicalUsesSeoField(): void
    {
        $record = $this->recordWithSeoData(['canonical' => 'https://example.test/seo']);
        $seo = $this->makeSeo('record', record: $record);

        self::assertSame('https://example.test/seo', $seo->canonical());
    }

    public function testCanonicalFallsBackToConfiguredDefault(): void
    {
        $seo = $this->makeSeo('record', [
            'default' => ['title' => '', 'description' => '', 'keywords' => '', 'canonical' => 'https://example.test/c'],
        ]);

        self::assertSame('https://example.test/c', $seo->canonical());
    }

    public function testCanonicalDefaultsToRequestUri(): void
    {
        $seo = $this->makeSeo('record');

        self::assertSame('http://localhost/', $seo->canonical());
    }

    // --- override "missing key" guard (d4617ec) ----------------------------

    public function testOverrideWithMissingKeysDoesNotShortCircuitOtherGetters(): void
    {
        // Only `title` is overridden; every other getter must ignore the override
        // and continue down its own fallback chain instead of erroring.
        $seo = $this->makeSeo('record', [
            'override_default' => ['record' => ['title' => 'Only Title']],
        ], payoff: 'Payoff');

        self::assertSame('Only Title', $seo->title());
        self::assertSame('Payoff', $seo->description());
        self::assertSame('', $seo->keywords());
        self::assertSame('website', $seo->ogtype());
        self::assertSame('index, follow', $seo->robots());
        self::assertSame('', $seo->image());
    }

    // --- malformed `seo` field payloads ------------------------------------

    /**
     * The `seo` field holds a raw JSON string maintained by the editor's JavaScript,
     * but nothing guarantees it: fixtures, imports and API writes can put anything
     * in there. A value that does not decode to an array must degrade to "no SEO
     * data" and let the normal fallback chain run, not blow up the whole page.
     */
    #[DataProvider('malformedSeoPayloadProvider')]
    public function testMalformedSeoFieldFallsBackToRecordFields(string $payload): void
    {
        $record = $this->record([
            'seo' => $payload,
            'title' => 'Record Title',
            'description' => 'Record description',
        ]);
        $seo = $this->makeSeo('record', record: $record);

        self::assertSame('Record Title', $seo->title());
        self::assertSame('Record description', $seo->description());
        self::assertSame('', $seo->keywords());
        self::assertSame('website', $seo->ogtype());
        self::assertSame('index, follow', $seo->robots());
    }

    /**
     * @return array<string, array{string}>
     */
    public static function malformedSeoPayloadProvider(): array
    {
        return [
            'not json at all' => ['this is not json'],
            'truncated json' => ['{"title": "Half a doc'],
            'json scalar int' => ['5'],
            'json scalar string' => ['"just a string"'],
            'json null' => ['null'],
            'json list' => ['["title", "description"]'],
        ];
    }

    // --- malformed encoding -------------------------------------------------

    /**
     * cleanUp() collapses whitespace with a `/u` regex, which returns null on
     * malformed UTF-8 rather than throwing. Returning that from a `string` method
     * is a TypeError, so a single badly encoded byte anywhere in the content
     * (a legacy import, a mangled paste) would take the page down.
     */
    public function testMalformedUtf8InRecordTitleDoesNotFatal(): void
    {
        $record = $this->record(['title' => "Caf\xC3\x28e"]);
        $seo = $this->makeSeo('record', record: $record, siteName: 'Acme');

        self::assertIsString($seo->title());
    }

    public function testMalformedUtf8InConfiguredDefaultDoesNotFatal(): void
    {
        $seo = $this->makeSeo('record', [
            'default' => ['title' => "Caf\xC3\x28e", 'description' => '', 'keywords' => ''],
        ]);

        self::assertIsString($seo->title());
    }

    // --- incomplete config --------------------------------------------------

    /**
     * Bolt copies an extension's default config to config/extensions/ exactly once
     * and never merges it again (ConfigTrait::initializeConfig), so a site's config
     * file can legitimately lack any key — an older copy, or one the user commented
     * out. A missing length key used to reach Html::trimText() as null, which is a
     * TypeError under strict_types. Fall back to the documented defaults instead.
     */
    public function testMissingDescriptionLengthFallsBackToDefault(): void
    {
        $config = $this->defaultConfig();
        unset($config['description_length']);

        $seo = $this->makeSeo('record', $config, record: $this->record([
            'description' => str_repeat('word ', 60),
        ]), mergeDefaults: false);

        $description = $seo->description();

        self::assertLessThanOrEqual(158, mb_strlen($description));
        self::assertStringEndsWith('…', $description);
    }

    public function testMissingKeywordsLengthDoesNotTruncate(): void
    {
        $config = $this->defaultConfig();
        unset($config['keywords_length']);

        $seo = $this->makeSeo('record', array_merge($config, [
            'override_default' => ['record' => ['keywords' => 'keyword one, keyword two']],
        ]), mergeDefaults: false);

        self::assertSame('keyword one, keyword two', $seo->keywords());
    }

    // --- metatags ----------------------------------------------------------

    public function testMetatagsRendersTemplateAsMarkup(): void
    {
        $captured = [];
        $twig = $this->createMock(Environment::class);
        $twig->method('getGlobals')->willReturn([]);
        $twig->method('render')->willReturnCallback(function (string $template, array $vars) use (&$captured): string {
            $captured = $vars;

            return '<meta name="rendered">';
        });

        $seo = $this->makeSeo('record', siteName: 'Acme', twig: $twig);
        $markup = $seo->metatags();

        self::assertInstanceOf(Markup::class, $markup);
        self::assertSame('<meta name="rendered">', (string) $markup);
        self::assertSame(
            ['title', 'description', 'keywords', 'image', 'robots', 'ogtype', 'canonical'],
            array_keys($captured)
        );
        self::assertSame('Acme', $captured['title']);
    }

    public function testMetatagsUsesConfiguredTemplate(): void
    {
        $captured = null;
        $twig = $this->createMock(Environment::class);
        $twig->method('getGlobals')->willReturn([]);
        $twig->method('render')->willReturnCallback(function (string $template) use (&$captured): string {
            $captured = $template;

            return '';
        });

        $seo = $this->makeSeo('record', ['templates' => ['meta' => '@custom/tags.html.twig']], twig: $twig);
        $seo->metatags();

        self::assertSame('@custom/tags.html.twig', $captured);
    }

    public function testCleanUpReturnsEmptyStringForNullSitename(): void
    {
        // No override, no default title, and a null sitename/payoff must not error.
        $seo = $this->makeSeo('record', siteName: null, payoff: null);

        self::assertSame('', $seo->title());
        self::assertSame('', $seo->description());
    }
}
