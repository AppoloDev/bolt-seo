<?php

declare(strict_types=1);

namespace Appolo\BoltSeo\Tests\Twig;

use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Twig\TwigFunction;

/**
 * Renders templates/_metatags.html.twig through a real Twig environment.
 *
 * The Seo tests mock Twig\Environment, so nothing there exercises the template
 * itself. The two Bolt-provided pieces the template depends on — the `htmllang()`
 * function and the `config` global — are stubbed here.
 */
class MetaTagsTemplateTest extends TestCase
{
    private function render(array $vars = []): string
    {
        $twig = new Environment(new FilesystemLoader(\dirname(__DIR__, 2) . '/templates'));

        $twig->addFunction(new TwigFunction('htmllang', static fn (): string => 'en-GB'));
        $twig->addGlobal('config', new class() {
            public function get(string $path): ?string
            {
                return $path === 'general/sitename' ? 'Acme' : null;
            }
        });

        return $twig->render('_metatags.html.twig', array_merge([
            'title' => 'Page title',
            'description' => 'Page description',
            'keywords' => '',
            'image' => '',
            'robots' => 'index, follow',
            'ogtype' => 'website',
            'canonical' => 'https://example.test/page',
        ], $vars));
    }

    /**
     * The keywords tag used to be nested inside the description `if`, so a page
     * with keywords but no description emitted neither. Keywords are an
     * independent tag and must not depend on the description being present.
     */
    public function testKeywordsAreRenderedWithoutADescription(): void
    {
        $html = $this->render(['description' => '', 'keywords' => 'alpha, beta']);

        self::assertStringContainsString('<meta name="keywords" content="alpha, beta"/>', $html);
        self::assertStringNotContainsString('name="description"', $html);
    }

    public function testKeywordsAndDescriptionAreRenderedTogether(): void
    {
        $html = $this->render(['description' => 'Page description', 'keywords' => 'alpha, beta']);

        self::assertStringContainsString('<meta name="description" content="Page description"/>', $html);
        self::assertStringContainsString('<meta name="keywords" content="alpha, beta"/>', $html);
    }

    public function testKeywordsTagIsOmittedWhenEmpty(): void
    {
        $html = $this->render(['keywords' => '']);

        self::assertStringNotContainsString('name="keywords"', $html);
    }

    public function testDescriptionTagsAreOmittedWhenEmpty(): void
    {
        $html = $this->render(['description' => '']);

        self::assertStringNotContainsString('name="description"', $html);
        self::assertStringNotContainsString('og:description', $html);
        self::assertStringNotContainsString('twitter:description', $html);
    }

    public function testCoreTagsAreAlwaysRendered(): void
    {
        $html = $this->render();

        self::assertStringContainsString('<meta property="og:locale" content="en_GB" />', $html);
        self::assertStringContainsString('<meta property="og:title" content="Page title">', $html);
        self::assertStringContainsString('<meta property="og:url" content="https://example.test/page">', $html);
        self::assertStringContainsString('<meta property="og:site_name" content="Acme" />', $html);
        self::assertStringContainsString('<meta name="robots" content="index, follow" />', $html);
    }

    public function testImageTagsAreRenderedOnlyWhenAnImageIsSet(): void
    {
        self::assertStringNotContainsString('og:image', $this->render(['image' => '']));

        $html = $this->render(['image' => 'https://example.test/i.jpg']);

        self::assertStringContainsString('<meta property="og:image" content="https://example.test/i.jpg" />', $html);
        self::assertStringContainsString('<meta property="twitter:image" content="https://example.test/i.jpg" />', $html);
    }
}
