<?php

declare(strict_types=1);

namespace Appolo\BoltSeo\Twig;

use Appolo\BoltSeo\Seo\ContentField;
use Bolt\Configuration\Config;
use Bolt\Configuration\Content\ContentType;
use Bolt\Entity\Content;
use Bolt\Entity\Field;
use Bolt\Extension\ExtensionInterface;
use Bolt\Extension\ExtensionRegistry;
use Bolt\Utils\Html;
use Illuminate\Support\Collection;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class SeoExtension extends AbstractExtension
{
    public function __construct(
        private readonly ExtensionRegistry $extensionRegistry,
        private readonly TranslatorInterface $translator,
        private readonly Config $config
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('seoGetConfig', $this->seoGetConfig(...)),
            new TwigFunction('seoFieldValue', $this->seoFieldValue(...)),
            new TwigFunction('seoFieldDefinition', $this->seoFieldDefinition(...)),
            new TwigFunction('seoField', $this->seoField(...)),
        ];
    }

    public function seoGetConfig(): array
    {
        return $this->getExtensionConfig()->toArray();
    }

    public function seoField(Content $content, string $field): ?string
    {
        $fieldsConfig = $this->getExtensionConfig()->get('fields');
        if (! isset($fieldsConfig[$field])) {
            return null;
        }

        $field = ContentField::getField($content, $fieldsConfig[$field]);

        if (! $field instanceof Field) {
            return null;
        }

        return Html::trimText($field->__toString(), 300);
    }

    public function seoFieldValue(Content $content, string $field): string
    {
        switch ($field) {
            case 'slug':
                $seoField = $this->seoField($content, 'slug');

                return $seoField ?: $this->translator->trans('default-title');
            case 'title':
                $seoField = $this->seoField($content, 'title');

                return $seoField ?: $this->translator->trans('Default title');
            case 'description':
                $description = '
                    Lorem ipsum dolor sit amet, consectetur adipiscing elit.
                    Sed cursus purus lacus, eget commodo quam finibus luctus. Aliquam odio nibh, commodo sit amet dui in.
                ';
                $seoField = $this->seoField($content, 'description');

                return $seoField ?: $description;
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
        $extension = $this->getExtension();

        return $extension->getConfig();
    }
}
