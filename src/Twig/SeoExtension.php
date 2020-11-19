<?php

declare(strict_types=1);

namespace Appolo\BoltSeo\Twig;

use Appolo\BoltSeo\Extension;
use Appolo\BoltSeo\Seo\ContentField;
use Bolt\Configuration\Config;
use Bolt\Configuration\Content\ContentType;
use Bolt\Entity\Content;
use Bolt\Entity\Field;
use Bolt\Extension\ExtensionInterface;
use Bolt\Extension\ExtensionRegistry;
use Symfony\Contracts\Translation\TranslatorInterface;
use Tightenco\Collect\Support\Collection;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class SeoExtension extends AbstractExtension
{
    /** @var ExtensionRegistry */
    private $extensionRegistry;
    /** @var TranslatorInterface */
    private $translator;
    /** @var Config */
    private $config;

    public function __construct(
        ExtensionRegistry $extensionRegistry,
        TranslatorInterface $translator,
        Config $config
    ) {
        $this->extensionRegistry = $extensionRegistry;
        $this->translator = $translator;
        $this->config = $config;
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('seoGetConfig', [$this, 'seoGetConfig']),
            new TwigFunction('seoFieldValue', [$this, 'seoFieldValue']),
            new TwigFunction('seoFieldDefinition', [$this, 'seoFieldDefinition']),
            new TwigFunction('seoField', [$this, 'seoField']),
        ];
    }

    public function seoGetConfig(): array
    {
        return $this->getExtensionConfig()->toArray();
    }

    public function seoField(Content $content, string $field): ?Field
    {
        $fieldsConfig = $this->getExtensionConfig()->get('fields');
        if (! isset($fieldsConfig[$field])) {
            return null;
        }

        return ContentField::getField($content, $fieldsConfig[$field]);
    }

    public function seoFieldValue(Content $content, string $field): string
    {
        switch ($field) {
            case 'slug':
                $seoField = $this->seoField($content, 'slug');

                return $seoField && $seoField->__toString() ? $seoField->__toString() : $this->translator->trans('default-title');
            case 'title':
                $title = $this->translator->trans('Default title');
                $seoField = $this->seoField($content, 'title');

                return $seoField && $seoField->__toString() ? $seoField->__toString() : $title;
            case 'description':
                $description = '
                    Lorem ipsum dolor sit amet, consectetur adipiscing elit. 
                    Sed cursus purus lacus, eget commodo quam finibus luctus. Aliquam odio nibh, commodo sit amet dui in.
                ';
                $seoField = $this->seoField($content, 'description');

                return $seoField && $seoField->__toString() ? $seoField->__toString() : $description;
            case 'postfix':
                if ($this->getExtensionConfig()->get('title_postfix') !== false) {
                    $titleSeparator = $this->getExtensionConfig()->get('title_separator') ?: '-';

                    $titlePostfix = $this->getExtensionConfig()->get('title_postfix') !== ''
                        ? $this->getExtensionConfig()->get('title_postfix')
                        : $this->config->get('general/sitename')
                    ;

                    return " {$titleSeparator} {$titlePostfix}";
                }

                return '';
        }

        return '';
    }

    public function seoFieldDefinition(Content $content, string $field): ?ContentType
    {
        $fieldsConfig = $this->getExtensionConfig()->get('fields');
        if (! isset($fieldsConfig[$field])) {
            return null;
        }

        return ContentField::getFieldDefinition($content, $fieldsConfig[$field]);
    }

    private function getExtension(): ExtensionInterface
    {
        return $this->extensionRegistry->getExtension('Appolo\\BoltSeo');
    }

    private function getExtensionConfig(): Collection
    {
        /** @var Extension $extension */
        $extension = $this->getExtension();

        return $extension->getConfig();
    }
}
