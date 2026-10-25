<?php

declare(strict_types=1);

namespace Appolo\BoltSeo\Seo;

use Bolt\Configuration\Config;
use Bolt\Configuration\Content\ContentType;
use Bolt\Entity\Content;
use Bolt\Entity\Field;
use Bolt\Twig\HtmlExtension;
use Bolt\Utils\Html;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;
use Twig\Markup;

/**
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

    /** @var array<mixed>|null */
    protected ?array $defaultsOverride = null;

    /** @var array<mixed> */
    protected array $seoData = [];

    /**
     * @param Collection<string, mixed> $config
     */
    public function __construct(
        private readonly Environment $twig,
        private readonly Collection $config,
        private readonly Config $boltConfig,
        private readonly Request $request,
        private readonly TranslatorInterface $translator
    ) {
        // `templates.meta` must be a template name; anything else keeps the default.
        $templateMetas = ConfigValue::string(ConfigValue::array($this->config->get('templates'))['meta'] ?? null);
        if ($templateMetas !== null && $templateMetas !== '') {
            $this->templateMetas = $templateMetas;
        }

        // A `_route` that is not text is treated as no route, like a 404 page.
        $routeType = $this->request->attributes->get('_route');
        $this->routeType = is_string($routeType) ? $routeType : null;
    }

    public function initialize(): void
    {
        if (! $this->defaultsOverride && isset($this->config['override_default'])) {
            $overrides = ConfigValue::array($this->config['override_default']);
            // Without a matched route, PHP looked up a null key as '', so keep that.
            $routeKey = $this->routeType ?? '';
            if (isset($overrides[$routeKey])) {
                // An override entry that is not a map (e.g. `homepage: 'Home'`) sets nothing.
                $this->defaultsOverride = $this->resolveLocaleOverride(ConfigValue::array($overrides[$routeKey]));
            } elseif (in_array($this->routeType, ['listing', 'listing_locale'], true)) {
                $slug = $this->request->get('contentTypeSlug');
                if (is_string($slug) && isset($overrides[$slug])) {
                    $this->defaultsOverride = $this->resolveLocaleOverride(ConfigValue::array($overrides[$slug]));
                }
            } elseif (is_string($this->routeType) && str_ends_with($this->routeType, '_locale')) {
                // Localized routes are named `<route>_locale` (e.g. `homepage_locale`).
                // Fall back to the base route key so a single `homepage` override (with an
                // optional `locales:` block) covers both the default and localized pages.
                $baseRoute = mb_substr($this->routeType, 0, -mb_strlen('_locale'));
                if (isset($overrides[$baseRoute])) {
                    $this->defaultsOverride = $this->resolveLocaleOverride(ConfigValue::array($overrides[$baseRoute]));
                }
            }
        }

        switch ($this->routeType) {
            case 'listing':
            case 'listing_locale':
                // Without a content type slug there is no content type to name the page after.
                $contentTypeSlug = $this->request->get('contentTypeSlug');
                $this->contentType = is_string($contentTypeSlug) ? $this->boltConfig->getContentType($contentTypeSlug) : null;
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
        if ($this->record instanceof Content) {
            return;
        }

        // A theme or another extension can set a `record` global that is not a
        // Content entity; it has no SEO data to offer, so ignore it.
        $record = $this->twig->getGlobals()['record'] ?? null;
        if (! $record instanceof Content) {
            return;
        }

        $this->record = $record;
        $field = $this->getSeoField($record);

        // The `seo` field holds a raw JSON string maintained by the editor's
        // JavaScript, but nothing guarantees that: fixtures, imports and API writes
        // can store anything. Anything that does not decode to an array degrades to
        // "no SEO data" so the normal fallback chain runs instead of fataling.
        $decoded = $field instanceof Field && $field->__toString()
            ? json_decode($field->__toString(), true)
            : [];

        $this->seoData = is_array($decoded) ? $decoded : [];
    }

    public function title(): string
    {
        $this->initialize();

        $overrideTitle = $this->overrideValue('title');
        if ($overrideTitle !== '') {
            return $this->cleanUp($overrideTitle . $this->postfixTitle());
        }

        switch ($this->routeType) {
            case 'listing':
            case 'listing_locale':
                // A singleton rendered on the listing route carries its own SEO
                // field and title; prefer those before falling back to the content
                // type name (which is all a real collection listing has).
                $seoTitle = $this->seoValue('title');
                if ($seoTitle !== '') {
                    return $this->cleanUp($seoTitle . $this->postfixTitle());
                }

                if ($this->record) {
                    $field = $this->getField($this->record, 'title');
                    if ($field instanceof Field && $field->__toString() !== '') {
                        return $this->cleanUp($field->__toString() . $this->postfixTitle());
                    }
                }

                if ($this->contentType) {
                    // A name that is not text counts as empty, as a missing name always did.
                    return $this->cleanUp(
                        ConfigValue::string($this->contentType->get('name')) . $this->postfixTitle()
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
                $seoTitle = $this->seoValue('title');
                if ($seoTitle !== '') {
                    return $this->cleanUp($seoTitle . $this->postfixTitle());
                }

                if ($this->record) {
                    $field = $this->getField($this->record, 'title');
                    if ($field instanceof Field && $field->__toString() !== '') {
                        return $this->cleanUp($field->__toString() . $this->postfixTitle());
                    }
                }
                break;
        }

        $defaultTitle = $this->defaultValue('title');
        if ($defaultTitle !== '') {
            return $this->cleanUp($defaultTitle);
        }

        // Bolt's sitename and payoff are read like this extension's config: a number
        // (`sitename: 2024`) is text, and anything else that is not text is empty.
        // A number used to be a TypeError.
        return $this->cleanUp(ConfigValue::string($this->boltConfig->get('general/sitename')));
    }

    public function description(): string
    {
        $this->initialize();

        $description = $this->overrideValue('description');
        if ($description !== '') {
            return $this->trimDescription($description);
        }

        $description = $this->seoValue('description');
        if ($description !== '') {
            return $this->trimDescription($this->cleanUp($description));
        }

        if ($this->record) {
            $field = $this->getField($this->record, 'description');
            if ($field instanceof Field && $field->__toString() !== '') {
                $description = $this->cleanUp($field->__toString());
                return $this->trimDescription($description);
            }
        }

        $description = $this->defaultValue('description');
        if ($description !== '') {
            $description = $this->cleanUp($description);
        } else {
            $description = $this->cleanUp(ConfigValue::string($this->boltConfig->get('general/payoff')));
        }

        return $this->trimDescription($description);
    }

    public function keywords(): string
    {
        $this->initialize();

        $keywords = $this->overrideValue('keywords');
        if ($keywords !== '') {
            return $this->trimKeywords($keywords);
        }

        $keywords = $this->seoValue('keywords');
        if ($keywords !== '') {
            return $this->trimKeywords($this->cleanUp($keywords));
        }

        return $this->trimKeywords($this->cleanUp($this->defaultValue('keywords')));
    }

    public function ogtype(): string
    {
        $this->initialize();

        $ogtype = $this->overrideValue('ogtype');
        if ($ogtype !== '') {
            return $ogtype;
        }

        $ogtype = $this->seoValue('og');
        if ($ogtype !== '') {
            return $this->cleanUp($ogtype);
        }

        $ogtype = $this->defaultValue('ogtype');
        if ($ogtype !== '') {
            return $this->cleanUp($ogtype);
        }

        return 'website';
    }

    public function robots(): string
    {
        $this->initialize();

        $robots = $this->overrideValue('robots');
        if ($robots !== '') {
            return $robots;
        }

        $robots = $this->seoValue('robots');
        if ($robots !== '') {
            return $this->cleanUp($robots);
        }

        $robots = $this->defaultValue('robots');
        if ($robots !== '') {
            return $this->cleanUp($robots);
        }
        return 'index, follow';
    }

    public function image(): string
    {
        $this->initialize();

        $image = $this->overrideValue('image');
        if ($image !== '') {
            return $image;
        }

        if ($this->record) {
            $field = $this->getField($this->record, 'image');
            if ($field instanceof Field && $field->__toString() !== '') {
                return $this->request->getSchemeAndHttpHost() . $this->cleanUp($field->__toString());
            }
        }

        $image = $this->defaultValue('image');
        if ($image !== '') {
            return $this->cleanUp($image);
        }

        // getExtras() builds every link and excerpt of the record, so call it once.
        // An extras image without a url that is text is skipped; a missing url
        // used to be a TypeError.
        if ($this->record instanceof Content) {
            $extrasImage = $this->record->getExtras()['image'] ?? null;
            $url = is_array($extrasImage) ? ConfigValue::string($extrasImage['url'] ?? null) : null;
            if ($url !== null && $url !== '') {
                return $url;
            }
        }

        return '';
    }

    public function canonical(): string
    {
        $this->initialize();

        $canonical = $this->overrideValue('canonical');
        if ($canonical !== '') {
            return $canonical;
        }

        $canonical = $this->seoValue('canonical');
        if ($canonical !== '') {
            return $this->cleanUp($canonical);
        }

        $canonical = $this->defaultValue('canonical');
        if ($canonical !== '') {
            return $this->cleanUp($canonical);
        }

        // Use the same URL as Bolt's <link rel="canonical">, which honours
        // `general/canonical` and `remove_default_locale_on_canonical` and drops
        // the query string. Bolt's Canonical service is private, so it is reached
        // through its public `canonical()` Twig function. It returns null when no
        // route has matched.
        if ($this->twig->hasExtension(HtmlExtension::class)) {
            $canonical = $this->twig->getExtension(HtmlExtension::class)->canonical();
            if ($canonical !== null) {
                return $canonical;
            }
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

    private function trimDescription(string $description): string
    {
        $length = $this->configLength('description_length');

        return $length !== null ? Html::trimText($description, $length) : $description;
    }

    /**
     * `keywords_length: 0` is the shipped default and means "the keywords field is
     * disabled in the editor", not "truncate to nothing". Html::trimText() treats a
     * length of 0 as a real limit and would chop the last character off and append
     * an ellipsis, so only truncate when a positive limit is configured.
     */
    private function trimKeywords(string $keywords): string
    {
        $length = $this->configLength('keywords_length');

        if ($length === null || $length <= 0) {
            return $keywords;
        }

        return Html::trimText($keywords, $length);
    }

    /**
     * Read a numeric config key defensively. Bolt copies an extension's default
     * config to config/extensions/ exactly once and never merges it again, so a
     * site's config file can be missing any key — an older copy, or one the user
     * commented out. Reading such a key as an int argument would be a TypeError, so
     * a missing or non-numeric value is treated as unset rather than duplicating
     * config.yaml's defaults here.
     */
    private function configLength(string $key): ?int
    {
        $value = $this->config->get($key);

        return is_numeric($value) ? (int) $value : null;
    }

    private function postfixTitle(): string
    {
        $titlePostfix = $this->config->get('title_postfix');
        if ($titlePostfix === false) {
            return '';
        }

        // A postfix that is not text, like `true` (which used to print `1`) or a
        // list, means no postfix, as does a sitename that is not text.
        $postfix = ConfigValue::string(
            $titlePostfix !== '' ? $titlePostfix : $this->boltConfig->get('general/sitename')
        );

        // Nothing to append (no configured postfix and no sitename): omit the
        // separator too — there is nothing to separate.
        if ($postfix === null || $postfix === '') {
            return '';
        }

        // A separator that is not text is left out, like a missing `title_separator`.
        $separator = ConfigValue::string($this->config->get('title_separator'));
        if ($separator === '') {
            $separator = '|';
        }

        return sprintf(' %s %s', $separator, $postfix);
    }

    /**
     * Merge an optional per-locale override (`locales: { en: { ... } }`) over the
     * base override for the current request locale. Locale values win; anything not
     * set for the locale falls back to the base values.
     *
     * @param array<mixed> $override
     * @return array<mixed>
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

    /*
     * The three sources of a meta value, read with the rules of ConfigValue: ''
     * means "not set here, try the next source".
     */

    private function overrideValue(string $key): string
    {
        return ConfigValue::string($this->defaultsOverride[$key] ?? null) ?? '';
    }

    private function seoValue(string $key): string
    {
        return ConfigValue::string($this->seoData[$key] ?? null) ?? '';
    }

    private function defaultValue(string $key): string
    {
        return ConfigValue::string(ConfigValue::array($this->config->get('default'))[$key] ?? null) ?? '';
    }

    private function cleanUp(?string $string): string
    {
        if ($string === null) {
            return '';
        }

        $string = strip_tags($string);
        // Rich-text fields store entities (`&nbsp;`, `&amp;`). Decode them, or the
        // template escapes them a second time and `&amp;nbsp;` reaches the page.
        $string = html_entity_decode($string, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $string = str_replace(["\r", "\n"], '', $string);

        // preg_replace() returns null on malformed UTF-8 instead of throwing, and
        // returning that would be a TypeError. A single bad byte from a legacy
        // import or a mangled paste must not take the page down, so fall back to
        // the uncollapsed string.
        return preg_replace('/\s+/u', ' ', $string) ?? $string;
    }

    private function getField(Content $content, string $field): ?Field
    {
        return ContentField::getField($content, ConfigValue::fieldNames($this->config->get('fields'), $field));
    }

    private function getSeoField(Content $content): ?Field
    {
        return ContentField::getField($content, ['seo']);
    }
}
