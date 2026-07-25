<?php

declare(strict_types=1);

namespace Appolo\BoltSeo\Tests\Seo;

use Appolo\BoltSeo\Seo\Seo;
use Bolt\Configuration\Config;
use Bolt\Configuration\Content\ContentType;
use Bolt\Entity\Content;
use Bolt\Entity\Field;
use Illuminate\Support\Collection;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

/**
 * Shared scaffolding for Seo tests: builds a Seo instance with mocked Bolt/Twig
 * collaborators and provides helpers to fabricate content types and records.
 */
abstract class SeoTestCase extends TestCase
{
    /**
     * @return array<string, mixed>
     */
    protected function defaultConfig(): array
    {
        return [
            'default' => ['title' => '', 'description' => '', 'keywords' => ''],
            'fields' => [
                'slug' => ['slug'],
                'title' => ['title', 'name'],
                'description' => ['introduction', 'teaser', 'description', 'body'],
                'keywords' => [],
                'image' => ['image'],
            ],
            'description_length' => 158,
            'keywords_length' => 255,
            'title_postfix' => false,
            'title_separator' => '|',
        ];
    }

    /**
     * @param array<string, mixed> $config
     * @param bool $mergeDefaults When false, $config is used verbatim so a test can
     *                            assert on a config that is *missing* a key entirely.
     */
    protected function makeSeo(
        string $route,
        array $config = [],
        ?string $contentTypeSlug = null,
        ?ContentType $contentType = null,
        ?Content $record = null,
        ?string $siteName = 'My Site',
        ?string $payoff = 'Welcome',
        string $locale = 'en',
        ?TranslatorInterface $translator = null,
        ?Request $request = null,
        ?Environment $twig = null,
        bool $mergeDefaults = true,
    ): Seo {
        if ($mergeDefaults) {
            $config = array_merge($this->defaultConfig(), $config);
        }

        if ($request === null) {
            $request = new Request(
                query: array_filter(['contentTypeSlug' => $contentTypeSlug]),
                server: ['HTTP_HOST' => 'localhost'],
            );
            $request->attributes->set('_route', $route);
            $request->setLocale($locale);
        }

        if ($twig === null) {
            $twig = $this->createMock(Environment::class);
            $twig->method('getGlobals')->willReturn($record !== null ? ['record' => $record] : []);
        }

        $boltConfig = $this->createMock(Config::class);
        $boltConfig->method('getContentType')->willReturn($contentType);
        $boltConfig->method('get')->willReturnCallback(static fn (string $path) => match ($path) {
            'general/sitename' => $siteName,
            'general/payoff' => $payoff,
            default => null,
        });

        return new Seo(
            $twig,
            new Collection($config),
            $boltConfig,
            $request,
            $translator ?? $this->createMock(TranslatorInterface::class),
        );
    }

    protected function contentType(string $name): ContentType
    {
        $contentType = $this->createMock(ContentType::class);
        $contentType->method('get')->willReturn($name);

        return $contentType;
    }

    /**
     * Build a Content mock whose fields render to the given values.
     *
     * @param array<string, string> $fields  field name => rendered string (e.g. ['title' => 'My Article'])
     * @param array<string, mixed>  $extras   value returned by getExtras()
     */
    protected function record(array $fields = [], array $extras = []): Content
    {
        $content = $this->createMock(Content::class);
        $content->method('hasField')->willReturnCallback(
            static fn (string $name) => array_key_exists($name, $fields)
        );
        $content->method('getField')->willReturnCallback(function (string $name) use ($fields): Field {
            $field = $this->createMock(Field::class);
            $field->method('__toString')->willReturn((string) ($fields[$name] ?? ''));

            return $field;
        });
        $content->method('getExtras')->willReturn($extras);

        return $content;
    }

    /**
     * Convenience: a record carrying a `seo` field with the given JSON-encoded SEO data.
     *
     * @param array<string, mixed> $seoData
     * @param array<string, string> $fields  additional plain fields
     */
    protected function recordWithSeoData(array $seoData, array $fields = []): Content
    {
        return $this->record(array_merge($fields, ['seo' => (string) json_encode($seoData)]));
    }
}
