<?php

declare(strict_types=1);

namespace Appolo\BoltSeo\Seo;

use Bolt\Configuration\Config;
use Bolt\Configuration\Content\ContentType;
use Bolt\Entity\Content;
use Bolt\Entity\Field;
use Bolt\Utils\Html;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;
use Twig\Markup;

/**
 * @package Appolo\BoltSeo\Seo
 * Priority:
 *  1. Override default
 *  2. Seo fields
 *  3. Content fields / ContentTypeSlug for listing && taxonomies
 *  4. Default title && payoff
 */
class Seo
{
    protected string $templateMetas = '@seo/_metatags.html.twig';
    protected ?Content $record = null;
    protected ?ContentType $contentType = null;
    protected ?string $routeType;
    protected ?array $defaultsOverride = null;
    protected array $seoData = [];

    public function __construct(
        private readonly Environment $twig,
        private readonly Collection $config,
        private readonly Config $boltConfig,
        private readonly Request $request,
        private readonly TranslatorInterface $translator
    ) {
        $templateConfig = $this->config->get('templates');
        if ($templateConfig && isset($templateConfig['meta']) && $templateConfig['meta'] !== '') {
            $this->templateMetas = $templateConfig['meta'];
        }

        $this->routeType = $this->request->attributes->get('_route');
    }

    public function initialize(): void
    {
        if (! $this->defaultsOverride && isset($this->config['override_default'])) {
            $overrides = $this->config['override_default'];
            if (isset($overrides[$this->routeType])) {
                $this->defaultsOverride = $this->resolveLocaleOverride($overrides[$this->routeType]);
            } elseif (in_array($this->routeType, ['listing', 'listing_locale'], true)) {
                $slug = $this->request->get('contentTypeSlug');
                if ($slug !== null && isset($overrides[$slug])) {
                    $this->defaultsOverride = $this->resolveLocaleOverride($overrides[$slug]);
                }
            } elseif (is_string($this->routeType) && str_ends_with($this->routeType, '_locale')) {
                // Localized routes are named `<route>_locale` (e.g. `homepage_locale`).
                // Fall back to the base route key so a single `homepage` override (with an
                // optional `locales:` block) covers both the default and localized pages.
                $baseRoute = mb_substr($this->routeType, 0, -mb_strlen('_locale'));
                if (isset($overrides[$baseRoute])) {
                    $this->defaultsOverride = $this->resolveLocaleOverride($overrides[$baseRoute]);
                }
            }
        }

        switch ($this->routeType) {
            case 'listing':
            case 'listing_locale':
                $contentTypeSlug = $this->request->get('contentTypeSlug');
                $this->contentType = $this->boltConfig->getContentType($contentTypeSlug);
                // A singleton content type is served on the listing route (its slug
                // equals the content type slug), but it renders a single record and
                // exposes a `record` global just like a detail route. Honour that
                // record's own SEO field instead of only the content type defaults.
                // Real collection listings expose `records` (plural), not `record`,
                // so they are unaffected and keep using the content type name.
                $this->loadRecordSeoFromTwig();
                break;
            default:
                $this->loadRecordSeoFromTwig();
                break;
        }
    }

    private function loadRecordSeoFromTwig(): void
    {
        if ($this->record || ! isset($this->twig->getGlobals()['record'])) {
            return;
        }

        $this->record = $this->twig->getGlobals()['record'];
        $field = $this->getSeoField($this->record);

        // The `seo` field holds a raw JSON string maintained by the editor's
        // JavaScript, but nothing guarantees that: fixtures, imports and API writes
        // can store anything. Anything that does not decode to an array degrades to
        // "no SEO data" so the normal fallback chain runs instead of fataling.
        $decoded = $field && $field->__toString()
            ? json_decode($field->__toString(), true)
            : [];

        $this->seoData = is_array($decoded) ? $decoded : [];
    }

    public function title(): string
    {
        $this->initialize();

        if (! empty($this->defaultsOverride['title'])) {
            return $this->cleanUp($this->defaultsOverride['title'] . $this->postfixTitle());
        }

        switch ($this->routeType) {
            case 'listing':
            case 'listing_locale':
                // A singleton rendered on the listing route carries its own SEO
                // field and title; prefer those before falling back to the content
                // type name (which is all a real collection listing has).
                if ($this->seoData && isset($this->seoData['title']) && $this->seoData['title'] !== '') {
                    return $this->cleanUp($this->seoData['title'] . $this->postfixTitle());
                }

                if ($this->record) {
                    $field = $this->getField($this->record, 'title');
                    if ($field && $field->__toString() !== '') {
                        return $this->cleanUp($field->__toString() . $this->postfixTitle());
                    }
                }

                if ($this->contentType) {
                    return $this->cleanUp(
                        $this->contentType->get('name') . $this->postfixTitle()
                    );
                }
                break;
            case 'taxonomy':
                return $this->cleanUp(
                    $this->translator->trans(
                        'general.phrase.overview-for',
                        [
                            '%slug%' => $this->request->get('slug'),
                        ]
                    ) . $this->postfixTitle()
                );
            default:
                if ($this->seoData && isset($this->seoData['title']) && $this->seoData['title'] !== '') {
                    return $this->cleanUp($this->seoData['title'] . $this->postfixTitle());
                }

                if ($this->record) {
                    $field = $this->getField($this->record, 'title');
                    if ($field && $field->__toString() !== '') {
                        return $this->cleanUp($field->__toString() . $this->postfixTitle());
                    }
                }
                break;
        }

        if (isset($this->config['default']['title']) && $this->config['default']['title'] !== '') {
            return $this->cleanUp($this->config['default']['title']);
        }

        return $this->cleanUp($this->boltConfig->get('general/sitename'));
    }

    public function description(): string
    {
        $this->initialize();

        if (! empty($this->defaultsOverride['description'])) {
            $description = $this->defaultsOverride['description'];
            return Html::trimText($description, $this->config['description_length']);
        }

        if ($this->seoData && isset($this->seoData['description']) && $this->seoData['description'] !== '') {
            $description = $this->cleanUp($this->seoData['description']);
            return Html::trimText($description, $this->config['description_length']);
        }

        if ($this->record) {
            $field = $this->getField($this->record, 'description');
            if ($field && $field->__toString() !== '') {
                $description = $this->cleanUp($field->__toString());
                return Html::trimText($description, $this->config['description_length']);
            }
        }

        if (isset($this->config['default']['description']) && $this->config['default']['description'] !== '') {
            $description = $this->cleanUp($this->config['default']['description']);
        } else {
            $description = $this->cleanUp($this->boltConfig->get('general/payoff'));
        }

        return Html::trimText($description, $this->config['description_length']);
    }

    public function keywords(): string
    {
        $this->initialize();

        if (! empty($this->defaultsOverride['keywords'])) {
            return $this->trimKeywords($this->defaultsOverride['keywords']);
        }

        if ($this->seoData && isset($this->seoData['keywords']) && $this->seoData['keywords'] !== '') {
            return $this->trimKeywords($this->cleanUp($this->seoData['keywords']));
        }

        if (isset($this->config['default']['keywords'])) {
            $keywords = $this->cleanUp($this->config['default']['keywords']);
        } else {
            $keywords = '';
        }

        return $this->trimKeywords($keywords);
    }

    public function ogtype()
    {
        $this->initialize();

        if (! empty($this->defaultsOverride['ogtype'])) {
            return $this->defaultsOverride['ogtype'];
        }

        if ($this->seoData && isset($this->seoData['og']) && $this->seoData['og'] !== '') {
            return $this->cleanUp($this->seoData['og']);
        }

        if (isset($this->config['default']['ogtype'])) {
            return $this->cleanUp($this->config['default']['ogtype']);
        }

        return 'website';
    }

    public function robots()
    {
        $this->initialize();

        if (! empty($this->defaultsOverride['robots'])) {
            return $this->defaultsOverride['robots'];
        }

        if ($this->seoData && isset($this->seoData['robots']) && $this->seoData['robots'] !== '') {
            return $this->cleanUp($this->seoData['robots']);
        }

        if (isset($this->config['default']['robots'])) {
            return $this->cleanUp($this->config['default']['robots']);
        }
        return 'index, follow';
    }

    public function image(): string
    {
        $this->initialize();

        if (! empty($this->defaultsOverride['image'])) {
            return $this->defaultsOverride['image'];
        }

        if ($this->record) {
            $field = $this->getField($this->record, 'image');
            if ($field && $field->__toString() !== '') {
                return $this->request->getSchemeAndHttpHost() . $this->cleanUp($field->__toString());
            }
        }

        if (isset($this->config['default']['image'])) {
            return $this->cleanUp($this->config['default']['image']);
        }

        if ($this->record instanceof Content && ! empty($this->record->getExtras()['image'])) {
            return $this->record->getExtras()['image']['url'];
        }

        return '';
    }

    public function canonical(): string
    {
        $this->initialize();

        if (! empty($this->defaultsOverride['canonical'])) {
            return $this->defaultsOverride['canonical'];
        }

        if ($this->seoData && isset($this->seoData['canonical']) && $this->seoData['canonical'] !== '') {
            return $this->cleanUp($this->seoData['canonical']);
        }

        if (isset($this->config['default']['canonical'])) {
            return $this->cleanUp($this->config['default']['canonical']);
        }

        return $this->request->getUri();
    }

    public function metatags(): Markup
    {
        $this->initialize();

        $vars = [
            'title' => $this->title(),
            'description' => $this->description(),
            'keywords' => $this->keywords(),
            'image' => $this->image(),
            'robots' => $this->robots(),
            'ogtype' => $this->ogtype(),
            'canonical' => $this->canonical(),
        ];

        $html = $this->twig->render($this->templateMetas, $vars);
        return new Markup($html, 'UTF-8');
    }

    /**
     * `keywords_length: 0` is the shipped default and means "the keywords field is
     * disabled in the editor", not "truncate to nothing". Html::trimText() treats a
     * length of 0 as a real limit and would chop the last character off and append
     * an ellipsis, so only truncate when a positive limit is configured.
     */
    private function trimKeywords(string $keywords): string
    {
        $length = $this->config['keywords_length'];

        if (! is_int($length) || $length <= 0) {
            return $keywords;
        }

        return Html::trimText($keywords, $length);
    }

    private function postfixTitle(): string
    {
        if ($this->config->get('title_postfix') === false) {
            return '';
        }

        $postfix = $this->config->get('title_postfix') !== ''
            ? $this->config->get('title_postfix')
            : $this->boltConfig->get('general/sitename');

        // Nothing to append (no configured postfix and no sitename): omit the
        // separator too — there is nothing to separate.
        if ($postfix === null || $postfix === '') {
            return '';
        }

        $separator = $this->config->get('title_separator') !== ''
            ? $this->config->get('title_separator')
            : '|';

        return sprintf(' %s %s', $separator, $postfix);
    }

    /**
     * Merge an optional per-locale override (`locales: { en: { ... } }`) over the
     * base override for the current request locale. Locale values win; anything not
     * set for the locale falls back to the base values.
     *
     * @param array<string, mixed> $override
     * @return array<string, mixed>
     */
    private function resolveLocaleOverride(array $override): array
    {
        if (! isset($override['locales']) || ! is_array($override['locales'])) {
            return $override;
        }

        $locale = $this->request->getLocale();
        $localeOverride = $override['locales'][$locale] ?? [];
        unset($override['locales']);

        return array_merge($override, is_array($localeOverride) ? $localeOverride : []);
    }

    private function cleanUp(?string $string): string
    {
        if ($string === null) {
            return '';
        }

        $string = strip_tags($string);
        $string = str_replace(["\r", "\n"], '', $string);

        // preg_replace() returns null on malformed UTF-8 instead of throwing, and
        // returning that would be a TypeError. A single bad byte from a legacy
        // import or a mangled paste must not take the page down, so fall back to
        // the uncollapsed string.
        return preg_replace('/\s+/u', ' ', $string) ?? $string;
    }

    private function getField(Content $content, string $field): ?Field
    {
        $fieldsConfig = $this->config->get('fields');
        if (! isset($fieldsConfig[$field])) {
            return null;
        }

        return ContentField::getField($content, $fieldsConfig[$field]);
    }

    private function getSeoField(Content $content): ?Field
    {
        return ContentField::getField($content, ['seo']);
    }
}
