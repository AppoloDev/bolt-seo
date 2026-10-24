<?php

declare(strict_types=1);

namespace Appolo\BoltSeo\Tests\Twig;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Twig\Loader\ArrayLoader;
use Twig\Loader\ChainLoader;
use Twig\Loader\FilesystemLoader;
use Twig\TwigFilter;
use Twig\TwigFunction;
use Twig\TwigTest;

/**
 * assets/seo/seo.js looks up the SEO inputs by id (`#seofields-title`, …) and adds
 * an event listener to each one without a null check. If templates/seo.html.twig
 * stops rendering one of them, the script throws while it starts, and the inputs
 * after it in the list stop saving. This renders the whole field, with the
 * keywords field both disabled (a hidden input) and enabled, and checks that every
 * id the script asks for is there exactly once.
 */
class SeoFieldScriptTest extends TestCase
{
    private static function renderField(int $keywordsLength): string
    {
        $twig = new Environment(new ChainLoader([
            new FilesystemLoader(\dirname(__DIR__, 2) . '/templates'),
            new ArrayLoader([
                '@bolt/_partials/fields/_base.html.twig' => '{% block field %}{% endblock %}',
                '@bolt/_partials/fields/_label.html.twig' => '',
            ]),
        ]));

        $twig->addTest(new TwigTest('json', static fn (): bool => true));
        $twig->addFilter(new TwigFilter('json_decode', static fn (string $json): mixed => json_decode($json, true)));
        $twig->addFunction(new TwigFunction('__', static fn (string $text): string => $text));
        $twig->addFunction(new TwigFunction('seoGetConfig', static fn (): array => ['keywords_length' => $keywordsLength]));
        $twig->addFunction(new TwigFunction('seoFieldDefinition', static fn (): null => null));
        $twig->addFunction(new TwigFunction('seoFieldValue', static fn (): string => ''));
        $twig->addFunction(new TwigFunction('canonical', static fn (): string => ''));

        return $twig->load('seo.html.twig')->renderBlock('field', [
            'name' => 'fields[seo]',
            'id' => 'field-seo',
            'value' => '',
            'record' => ['definition' => ['singular_slug' => 'page', 'record_route' => 'record']],
        ]);
    }

    /**
     * @return list<string>
     */
    private static function scriptInputIds(): array
    {
        $script = (string) file_get_contents(\dirname(__DIR__, 2) . '/assets/seo/seo.js');
        preg_match_all("/querySelector\\('#(seofields-[a-z]+)'\\)/", $script, $matches);

        return $matches[1];
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function keywordsLengths(): iterable
    {
        yield 'keywords disabled' => [0];
        yield 'keywords enabled' => [255];
    }

    #[DataProvider('keywordsLengths')]
    public function testEveryInputTheScriptUsesIsRendered(int $keywordsLength): void
    {
        $html = self::renderField($keywordsLength);

        $ids = self::scriptInputIds();
        self::assertNotEmpty($ids);

        foreach ($ids as $id) {
            self::assertSame(1, mb_substr_count($html, 'id="' . $id . '"'), "seo.html.twig should render #{$id} exactly once");
        }
    }
}
