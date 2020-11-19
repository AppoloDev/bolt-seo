<?php

declare(strict_types=1);

namespace Appolo\BoltSeo\Seo;

use Bolt\Configuration\Config;
use Bolt\Configuration\Content\ContentType;
use Bolt\Entity\Content;
use Bolt\Entity\Field;
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
            case 'record':
            case 'homepage':
                if(!$this->record) {
                    $this->record = isset($this->twig->getGlobals()['record'])
                        ? $this->twig->getGlobals()['record']
                        : null
                    ;

                    $field = $this->getSeoField($this->record);
                    $this->seoData = $field && $field->__toString() ? json_decode($field->__toString(), true) : null;
                }
                break;
            case 'listing':
                $contentTypeSlug = $this->request->get('contentTypeSlug');
                $this->contentType = $this->boltConfig->get('contenttypes/'.$contentTypeSlug);
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
            case 'record':
            case 'homepage':
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
            case 'listing':
                return $this->cleanUp(
                    $this->translator->trans(
                        'Listing of %contenttype%',
                        [
                            '%contenttype%' => $this->contentType->get('name')
                        ]
                    ).$this->postfixTitle()
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
        }

        return $this->cleanUp($this->boltConfig->get('general/sitename'));
    }

    /**
     * @TODO
     */
    public function description()
    {
        $this->initialize();

        if($this->defaultsOverride && $this->defaultsOverride['description']) {
            return $this->defaultsOverride['description'];
        }

        return '';
    }

    /**
     * @TODO
     */
    public function keywords()
    {
        return [];
    }

    /**
     * @TODO
     */
    public function ogtype()
    {
        return '';
    }

    /**
     * @TODO
     */
    public function robots()
    {
        return '';
    }

    /**
     * @TODO
     */
    public function metatags()
    {
        $this->initialize();

        $vars = [
            'title' => $this->title(),
            'description' => $this->description(),
            'keywords' => $this->keywords(),
            'image' => $this->findImage(),
            'robots' => $this->robots(),
            'ogtype' => $this->ogtype(),
            //$this->app['resources']->getUrl('canonicalurl'),
            'canonical' => ''
        ];

        $html = $this->twig->render($this->templateMetas, []);
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

    /**
     * @TODO
     * @return string
     */
    private function findImage()
    {
        return '';
    }

    /**
     * @TODO
     */
    private function findImageHelper($fieldname, $field = null, $imageField = null)
    {

    }

    /**
     * @TODO
     */
    public function setCanonical($canonical = '')
    {

    }

    /**
     * @TODO
     */
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
