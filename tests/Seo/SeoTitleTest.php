<?php

declare(strict_types=1);

namespace Appolo\BoltSeo\Tests\Seo;

use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Full fallback chain for Seo::title():
 *   override -> listing content type / taxonomy label / seo field / record field
 *   -> configured default -> sitename. Also covers the title postfix and cleanUp().
 */
class SeoTitleTest extends SeoTestCase
{
    public function testOverrideTitleWins(): void
    {
        $seo = $this->makeSeo('homepage', [
            'override_default' => [
                'homepage' => [
                    'title' => 'Override',
                ],
            ],
        ]);

        self::assertSame('Override', $seo->title());
    }

    public function testListingUsesContentTypeName(): void
    {
        $seo = $this->makeSeo('listing', contentTypeSlug: 'blog', contentType: $this->contentType('Blog'));

        self::assertSame('Blog', $seo->title());
    }

    public function testListingWithUnknownContentTypeFallsBackToSitename(): void
    {
        $seo = $this->makeSeo('listing', contentTypeSlug: 'ghost', contentType: null);

        self::assertSame('My Site', $seo->title());
    }

    public function testSingletonOnListingRouteUsesSeoFieldTitle(): void
    {
        // A singleton content type is served on the listing route (its slug equals
        // the content type slug) but renders a single record exposed as the `record`
        // global. Its own SEO title must win over the content type name.
        $record = $this->recordWithSeoData(['title' => 'About Us SEO'], ['title' => 'About']);

        $seo = $this->makeSeo(
            'listing',
            contentTypeSlug: 'about',
            contentType: $this->contentType('About CT'),
            record: $record,
        );

        self::assertSame('About Us SEO', $seo->title());
    }

    public function testSingletonOnListingRouteFallsBackToRecordTitleField(): void
    {
        // Singleton record without a SEO title falls back to its title field before
        // the content type name.
        $record = $this->record(['title' => 'About Field Title']);

        $seo = $this->makeSeo(
            'listing',
            contentTypeSlug: 'about',
            contentType: $this->contentType('About CT'),
            record: $record,
        );

        self::assertSame('About Field Title', $seo->title());
    }

    public function testSingletonOnListingLocaleRouteUsesSeoFieldTitle(): void
    {
        // The localized listing route (`listing_locale`) forwards a singleton the
        // same way `listing` does, keeping the `_route` attribute. Its own SEO title
        // must win over the content type name there too.
        $record = $this->recordWithSeoData(['title' => 'About Us SEO'], ['title' => 'About']);

        $seo = $this->makeSeo(
            'listing_locale',
            contentTypeSlug: 'about',
            contentType: $this->contentType('About CT'),
            record: $record,
        );

        self::assertSame('About Us SEO', $seo->title());
    }

    public function testTaxonomyUsesTranslatedOverviewLabel(): void
    {
        $translator = $this->createMock(TranslatorInterface::class);
        $translator->method('trans')->willReturn('Overview for news');

        $seo = $this->makeSeo('taxonomy', translator: $translator);

        self::assertSame('Overview for news', $seo->title());
    }

    public function testRecordUsesSeoFieldTitle(): void
    {
        $record = $this->recordWithSeoData(['title' => 'SEO Title'], ['title' => 'Field Title']);

        $seo = $this->makeSeo('record', record: $record);

        self::assertSame('SEO Title', $seo->title());
    }

    public function testRecordFallsBackToContentTitleField(): void
    {
        // No seo field -> use the configured title field of the record.
        $record = $this->record(['title' => 'Field Title']);

        $seo = $this->makeSeo('record', record: $record);

        self::assertSame('Field Title', $seo->title());
    }

    public function testFallsBackToConfiguredDefaultTitle(): void
    {
        $seo = $this->makeSeo('record', [
            'default' => ['title' => 'Default Title', 'description' => '', 'keywords' => ''],
        ]);

        self::assertSame('Default Title', $seo->title());
    }

    public function testFallsBackToSitenameWhenNoDefaultTitle(): void
    {
        $seo = $this->makeSeo('record', siteName: 'Acme Inc');

        self::assertSame('Acme Inc', $seo->title());
    }

    public function testPostfixUsesConfiguredSeparatorAndText(): void
    {
        $seo = $this->makeSeo('listing', [
            'title_postfix' => 'Brand',
            'title_separator' => '-',
        ], contentTypeSlug: 'blog', contentType: $this->contentType('Blog'));

        self::assertSame('Blog - Brand', $seo->title());
    }

    public function testPostfixDefaultsToSitenameWhenEmpty(): void
    {
        $seo = $this->makeSeo('listing', [
            'title_postfix' => '',
            'title_separator' => '',
        ], contentTypeSlug: 'blog', contentType: $this->contentType('Blog'), siteName: 'Acme');

        // empty separator -> '|', empty postfix -> sitename
        self::assertSame('Blog | Acme', $seo->title());
    }

    public function testPostfixOmitsSeparatorWhenResolvedValueIsEmpty(): void
    {
        // No configured postfix and no sitename to fall back to: the resolved postfix
        // is empty, so the separator must be omitted too (no dangling " | ").
        $seo = $this->makeSeo('listing', [
            'title_postfix' => '',
            'title_separator' => '|',
        ], contentTypeSlug: 'blog', contentType: $this->contentType('Blog'), siteName: null);

        self::assertSame('Blog', $seo->title());
    }

    public function testCleanUpStripsTagsAndCollapsesWhitespace(): void
    {
        $seo = $this->makeSeo('homepage', [
            'override_default' => ['homepage' => ['title' => "<b>Bold</b>  and\r\n spaced"]],
        ]);

        self::assertSame('Bold and spaced', $seo->title());
    }

    public function testCleanUpDecodesEntitiesSoTheTemplateEscapesThemOnlyOnce(): void
    {
        $record = $this->record(['title' => 'Fish&nbsp;&amp; Chips &quot;Deluxe&quot;']);

        $seo = $this->makeSeo('record', record: $record);

        self::assertSame('Fish & Chips "Deluxe"', $seo->title());
    }

    public function testCleanUpKeepsEncodedAngleBracketsAsText(): void
    {
        $record = $this->record(['title' => '<p>1 &lt; 2 &amp;&amp; 3 &gt; 2</p>']);

        $seo = $this->makeSeo('record', record: $record);

        self::assertSame('1 < 2 && 3 > 2', $seo->title());
    }
}
