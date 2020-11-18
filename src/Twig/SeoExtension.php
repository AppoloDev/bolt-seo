<?php

declare(strict_types=1);

namespace Appolo\BoltSeo\Twig;

use Bolt\Common\Json;
use Bolt\Configuration\Config;
use Bolt\Entity\Content;
use Bolt\Extension\ExtensionInterface;
use Bolt\Extension\ExtensionRegistry;
use Symfony\Contracts\Translation\TranslatorInterface;
use Tightenco\Collect\Support\Collection;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;
use Webmozart\PathUtil\Path;

class SeoExtension extends AbstractExtension
{
    /**
     * @var ExtensionRegistry
     */
    private $extensionRegistry;
    /**
     * @var TranslatorInterface
     */
    private $translator;
    /**
     * @var Config
     */
    private $config;

    public function __construct(
        ExtensionRegistry $extensionRegistry,
        TranslatorInterface $translator,
        Config $config
    )
    {
        $this->extensionRegistry = $extensionRegistry;
        $this->translator = $translator;
        $this->config = $config;
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('seoGetConfig', [$this, 'seoGetConfig']),
            new TwigFunction('seoFieldValue', [$this, 'seoFieldValue']),
        ];
    }

    public function seoGetConfig(): array
    {
        return $this->getExtensionConfig()->toArray();
    }

    public function seoFieldValue(Content $content, string $field): string
    {
        $fieldsConfig = $this->getExtensionConfig()->get('fields');

        switch ($field) {
            case 'slug':
                if($content->hasField('slug')) {
                    return $content->getField('slug')->__toString();
                }

                return $this->translator->trans('default-title');
            case 'title':
                $title = $this->translator->trans('Default title');
                if (!isset($fieldsConfig['title'])) {
                    return $title;
                }

                foreach ($fieldsConfig['title'] as $fieldName) {
                    if ($content->hasField($fieldName)) {
                        return $content->getField($fieldName)->__toString();
                    }
                }

                return $title;
            case 'description':
                $description = '
                    Lorem ipsum dolor sit amet, consectetur adipiscing elit. 
                    Sed cursus purus lacus, eget commodo quam finibus luctus. Aliquam odio nibh, commodo sit amet dui in.
                ';
                if (!isset($fieldsConfig['description'])) {
                    return $description;
                }

                foreach ($fieldsConfig['description'] as $fieldName) {
                    if ($content->hasField($fieldName)) {
                        return $content->getField($fieldName)->__toString();
                    }
                }

                return $description;
            case 'postfix':
                if ($this->getExtensionConfig()->get('title_postfix') !== false) {

                    $titleSeparator = $this->getExtensionConfig()->get('title_separator')
                        ? $this->getExtensionConfig()->get('title_separator')
                        : '-';

                    $titlePostfix = $this->getExtensionConfig()->get('title_postfix') !== ''
                        ? $this->getExtensionConfig()->get('title_postfix')
                        : 'plop' //config.get('general/sitename')
                    ;

                    return " {$titleSeparator} {$titlePostfix}";
                }

                return '';
        }
    }

    private function getExtension(): ExtensionInterface
    {
        return $this->extensionRegistry->getExtension('Appolo\\BoltSeo');
    }

    private function getExtensionConfig(): Collection
    {
        return $this->getExtension()->getConfig();
    }
}
