<?php

declare(strict_types=1);

namespace Appolo\BoltSeo\Tests\Twig;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Translation\Loader\XliffFileLoader;
use Twig\Environment;
use Twig\Loader\ArrayLoader;
use Twig\Loader\ChainLoader;
use Twig\Loader\FilesystemLoader;
use Twig\TwigFilter;
use Twig\TwigFunction;
use Twig\TwigTest;

/**
 * Every string templates/seo.html.twig passes to `__()` is a translation key, so
 * rewording one in the template without updating translations/*.xlf leaves it
 * untranslated in every locale. This renders the whole field, with the optional
 * keywords field enabled, collects the keys, and checks each translation file has
 * a unit for every one of them.
 */
class SeoFieldTranslationsTest extends TestCase
{
    /**
     * @return list<string>
     */
    private static function translationKeys(): array
    {
        $keys = [];

        $twig = new Environment(new ChainLoader([
            new FilesystemLoader(\dirname(__DIR__, 2) . '/templates'),
            new ArrayLoader([
                '@bolt/_partials/fields/_base.html.twig' => '{% block field %}{% endblock %}',
                '@bolt/_partials/fields/_label.html.twig' => '',
            ]),
        ]));

        $twig->addTest(new TwigTest('json', static fn (): bool => true));
        $twig->addFilter(new TwigFilter('json_decode', static fn (string $json): mixed => json_decode($json, true)));
        $twig->addFunction(new TwigFunction('__', static function (string $text) use (&$keys): string {
            $keys[] = $text;

            return $text;
        }));
        $twig->addFunction(new TwigFunction('seoGetConfig', static fn (): array => ['keywords_length' => 255]));
        $twig->addFunction(new TwigFunction('seoFieldDefinition', static fn (): null => null));
        $twig->addFunction(new TwigFunction('seoFieldValue', static fn (): string => ''));
        $twig->addFunction(new TwigFunction('canonical', static fn (): string => ''));

        $twig->load('seo.html.twig')->renderBlock('field', [
            'name' => 'fields[seo]',
            'id' => 'field-seo',
            'value' => '',
            'record' => ['definition' => ['singular_slug' => 'page', 'record_route' => 'record']],
        ]);

        return array_values(array_unique($keys));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function translationFiles(): iterable
    {
        foreach (glob(\dirname(__DIR__, 2) . '/translations/messages.*.xlf') ?: [] as $file) {
            yield basename($file) => [$file];
        }
    }

    #[DataProvider('translationFiles')]
    public function testEveryFieldTextHasATranslation(string $file): void
    {
        $locale = explode('.', basename($file))[1];
        $catalogue = (new XliffFileLoader())->load($file, $locale);

        $keys = self::translationKeys();
        self::assertNotEmpty($keys);

        $missing = array_values(array_filter($keys, static fn (string $key): bool => ! $catalogue->has($key)));

        self::assertSame([], $missing, basename($file) . ' has no unit for these strings in seo.html.twig');
    }
}
