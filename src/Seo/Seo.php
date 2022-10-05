<?php

declare(strict_types=1);

namespace Appolo\BoltSeo\Seo;

use Bolt\Configuration\Config;
use Bolt\Configuration\Content\ContentType;
use Bolt\Entity\Content;
use Bolt\Entity\Field;
use Bolt\Utils\Html;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\Translation\TranslatorInterface;
use Tightenco\Collect\Support\Collection;
use Twig\Environment;
use Twig\Markup;

/**
 * Class Seo
 * @package Appolo\BoltSeo\Seo
 * Priority:
 *  1. Override default
 *  2. Seo fields
 *  3. Content fields / ContentTypeSlug for listing && taxonomies
 *  4. Default title && payoff
 */
class Seo
{
    /** @var string */
    protected $templateMetas = '@seo/_metatags.html.twig';
    /** @var Environment */
    private $twig;
    /** @var Collection */
    private $config;
    /** @var Config */
    private $boltConfig;
    /** @var TranslatorInterface */
    private $translator;
    /** @var Request $request */
    private $request;
    /** @var Content|null */
    protected $record;
    /** @var ContentType|null */
    protected $contentType;
    /** @var string */
    protected $routeType;
    /** @var array|null $defaultsOverride */
    protected $defaultsOverride;
    /** @var array */
    protected $seoData;

    public function __construct(
        Environment $twig,
        Collection $config,
        Config $boltConfig,
        Request $request,
        TranslatorInterface $translator
    ) {
        $this->twig = $twig;
        $this->config = $config;
        $this->boltConfig = $boltConfig;
        $this->request = $request;

        $templateConfig = $this->config->get('templates');
        if($templateConfig && isset($templateConfig['meta']) && $templateConfig['meta'] !== '') {
            $this->templateMetas = $templateConfig['meta'];
        }
        $this->routeType = $this->request->get('_route');
        $this->translator = $translator;
    }

    public function initialize()
    {
        if(
            !$this->defaultsOverride &&
            isset($this->config['override_default']) &&
            isset($this->config['override_default'][$this->routeType])
        ) {
            $this->defaultsOverride = $this->config['override_default'][$this->routeType];
        }

        switch ($this->routeType) {
            case 'listing':
                $contentTypeSlug = $this->request->get('contentTypeSlug');
                $this->contentType = $this->boltConfig->getContentType($contentTypeSlug);
                break;
            default:
                if(!$this->record && isset($this->twig->getGlobals()['record'])) {
                    $this->record = $this->twig->getGlobals()['record'];
                    $field = $this->getSeoField($this->record);
                    $this->seoData = $field && $field->__toString() ? json_decode($field->__toString(), true) : null;
                }
                break;
        }
    }

    public function title(): string
    {
        $this->initialize();

        if($this->defaultsOverride && $this->defaultsOverride['title']) {
            return $this->cleanUp($this->defaultsOverride['title'].$this->postfixTitle());
        }

        switch ($this->routeType) {
            case 'listing':
                return $this->cleanUp(
                    $this->contentType->get('name').$this->postfixTitle()
                );
            case 'taxonomy':
                return $this->cleanUp(
                    $this->translator->trans(
                        'general.phrase.overview-for',
                        [
                            '%slug%' =>$this->request->get('slug')
                        ]
                    ).$this->postfixTitle()
                );
            default:
                if($this->seoData && isset($this->seoData['title']) && $this->seoData['title'] !== '') {
                    return $this->cleanUp($this->seoData['title'].$this->postfixTitle());
                }

                if ($this->record) {
                    $field = $this->getField($this->record, 'title');
                    if ($field && $field->__toString() !== '') {
                        return $this->cleanUp($field->__toString().$this->postfixTitle());
                    }
                }
                break;
        }

        if(isset($this->config['default']['title']) && $this->config['default']['title'] !== '') {
            return $this->cleanUp($this->config['default']['title']);
        }

        return $this->cleanUp($this->boltConfig->get('general/sitename'));
    }

    public function description(): string
    {
        $this->initialize();

        if($this->defaultsOverride && $this->defaultsOverride['description']) {
            $description = $this->defaultsOverride['description'];
            return Html::trimText($description, $this->config['description_length']);
        }

        if($this->seoData && isset($this->seoData['description']) && $this->seoData['description'] !== '') {
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

        if(isset($this->config['default']['description']) && $this->config['default']['description'] !== '') {
            $description = $this->cleanUp($this->config['default']['description']);
        } else {
            $description = $this->cleanUp($this->boltConfig->get('general/payoff'));
        }

        return Html::trimText($description, $this->config['description_length']);
    }

    public function keywords(): string
    {
        $this->initialize();

        if($this->defaultsOverride && $this->defaultsOverride['keywords']) {
            $keywords = $this->defaultsOverride['keywords'];
            return Html::trimText($keywords, $this->config['keywords_length']);
        }

        if($this->seoData && isset($this->seoData['keywords']) && $this->seoData['keywords'] !== '') {
            $keywords = $this->cleanUp($this->seoData['keywords']);
            return Html::trimText($keywords, $this->config['keywords_length']);
        }

        if(isset($this->config['default']['keywords'])) {
            $keywords = $this->cleanUp($this->config['default']['keywords']);
        } else {
            $keywords = '';
        }

        return Html::trimText($keywords, $this->config['keywords_length']);
    }

    public function ogtype()
    {
        $this->initialize();

        if($this->defaultsOverride && $this->defaultsOverride['ogtype']) {
            return $this->defaultsOverride['ogtype'];
        }

        if($this->seoData && isset($this->seoData['og']) && $this->seoData['og'] !== '') {
            return $this->cleanUp($this->seoData['og']);
        }

        if(isset($this->config['default']['ogtype'])) {
            return $this->cleanUp($this->config['default']['ogtype']);
        }

        return 'website';
    }

    public function robots()
    {
        $this->initialize();

        if($this->defaultsOverride && $this->defaultsOverride['robots']) {
            return $this->defaultsOverride['robots'];
        }

        if($this->seoData && isset($this->seoData['robots']) && $this->seoData['robots'] !== '') {
            return $this->cleanUp($this->seoData['robots']);
        }

        if(isset($this->config['default']['robots'])) {
            return $this->cleanUp($this->config['default']['robots']);
        } else {
            return 'index, follow';
        }
    }

    public function image(): string
    {
        $this->initialize();

        if($this->defaultsOverride && $this->defaultsOverride['image']) {
            return $this->defaultsOverride['image'];
        }

        if ($this->record) {
            $field = $this->getField($this->record, 'image');
            if ($field && $field->__toString() !== '') {
                return $this->request->getSchemeAndHttpHost().$this->cleanUp($field->__toString());
            }
        }

        if(isset($this->config['default']['image'])) {
            return $this->cleanUp($this->config['default']['image']);
        }

        if ($this->record instanceof Content && !empty($this->record->getExtras()['image'])) {
            return $this->record->getExtras()['image']['url'];
        }

        return '';
    }

    public function canonical(): string
    {
        $this->initialize();

        if($this->defaultsOverride && $this->defaultsOverride['canonical']) {
            return $this->defaultsOverride['canonical'];
        }

        if($this->seoData && isset($this->seoData['canonical']) && $this->seoData['canonical'] !== '') {
            return $this->cleanUp($this->seoData['canonical']);
        }

        if(isset($this->config['default']['canonical'])) {
            return $this->cleanUp($this->config['default']['canonical']);
        }

        return $this->request->getUri();
    }

    public function metatags()
    {
        $this->initialize();

        $vars = [
            'title' => $this->title(),
            'description' => $this->description(),
            'keywords' => $this->keywords(),
            'image' => $this->image(),
            'robots' => $this->robots(),
            'ogtype' => $this->ogtype(),
            'canonical' => $this->canonical()
        ];

        $html = $this->twig->render($this->templateMetas, $vars);
        return new Markup($html, 'UTF-8');
    }

    private function postfixTitle(): string
    {
        if ($this->config->get('title_postfix') === false) {
            return '';
        } else {
            return sprintf(' %s %s',
                $this->config->get('title_separator') !== '' ? $this->config->get('title_separator') : '|',
                $this->config->get('title_postfix') !== '' ? $this->config->get('title_postfix') : $this->boltConfig->get('general/sitename')
            );
        }
    }

    private function cleanUp(string $string): string
    {
        $string = strip_tags($string);
        $string = str_replace(["\r", "\n"], '', $string);
        $string = preg_replace('/\s+/u', ' ', $string);

        return $string;
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
