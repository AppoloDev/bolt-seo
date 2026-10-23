<?php

declare(strict_types=1);

namespace Appolo\BoltSeo\Tests\Twig;

use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Twig\Loader\ArrayLoader;
use Twig\Loader\ChainLoader;
use Twig\Loader\FilesystemLoader;
use Twig\TwigFilter;
use Twig\TwigFunction;
use Twig\TwigTest;

/**
 * Renders the snippet preview block of templates/seo.html.twig through a real
 * Twig environment.
 *
 * The Bolt- and extension-provided pieces the template needs to compile are
 * stubbed: the parent field template, the `json` test, `json_decode`, `__()`, the
 * `seo*()` functions, and `canonical()`, which echoes the route and parameters it
 * was called with so the tests can assert on them.
 */
class SeoFieldTemplateTest extends TestCase
{
    private function renderSnippet(string $recordRoute, ?string $currentLocale = null): string
    {
        $twig = new Environment(new ChainLoader([
            new FilesystemLoader(\dirname(__DIR__, 2) . '/templates'),
            new ArrayLoader(['@bolt/_partials/fields/_base.html.twig' => '{% block field %}{% endblock %}']),
        ]));

        $twig->addFilter(new TwigFilter('json_decode', static fn (string $json): mixed => json_decode($json, true)));
        $twig->addTest(new TwigTest('json', static fn (): bool => true));
        $twig->addFunction(new TwigFunction('__', static fn (string $text): string => $text));
        $twig->addFunction(new TwigFunction('seoGetConfig', static fn (): array => []));
        $twig->addFunction(new TwigFunction('seoFieldDefinition', static fn (): null => null));
        $twig->addFunction(new TwigFunction('seoFieldValue', static fn (): string => ''));
        $twig->addFunction(new TwigFunction(
            'canonical',
            static fn (string $route, array $params): string => $route . '?' . http_build_query($params)
        ));

        return $twig->load('seo.html.twig')->renderBlock('seo_snippet', [
            'name' => 'fields[seo]',
            'record' => ['definition' => ['singular_slug' => 'page', 'record_route' => $recordRoute]],
            'currentlocale' => $currentLocale,
        ]);
    }

    public function testSnippetUrlUsesTheContentTypeRecordRoute(): void
    {
        $html = $this->renderSnippet('content_seo', 'en');

        self::assertStringContainsString(
            'data-base-url="content_seo?contentTypeSlug=page&amp;slugOrId=REPLACE&amp;_locale=en"',
            $html
        );
    }

    public function testSnippetUrlUsesTheEditedLocale(): void
    {
        $html = $this->renderSnippet('record_locale', 'nl');

        self::assertStringContainsString(
            'data-base-url="record_locale?contentTypeSlug=page&amp;slugOrId=REPLACE&amp;_locale=nl"',
            $html
        );
    }
}
