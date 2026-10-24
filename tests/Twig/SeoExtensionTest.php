<?php

declare(strict_types=1);

namespace Appolo\BoltSeo\Tests\Twig;

use Appolo\BoltSeo\Extension;
use Appolo\BoltSeo\Twig\SeoExtension;
use Bolt\Configuration\Config;
use Bolt\Configuration\Content\ContentType;
use Bolt\Entity\Content;
use Bolt\Entity\Field;
use Illuminate\Support\Collection;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Covers the Twig helpers used by the SEO field editor: config exposure, field
 * value resolution with fallbacks, the title postfix builder, and field
 * definition lookup.
 */
class SeoExtensionTest extends TestCase
{
    /**
     * @param array<string, mixed> $config
     */
    private function makeExtension(
        array $config,
        ?TranslatorInterface $translator = null,
        ?Config $boltConfig = null,
    ): SeoExtension {
        $extension = $this->createMock(Extension::class);
        $extension->method('getConfig')->willReturn(new Collection($config));

        $registry = $this->getMockBuilder(\Bolt\Extension\ExtensionRegistry::class)
            ->disableOriginalConstructor()
            ->getMock();
        $registry->method('getExtension')->willReturn($extension);

        return new SeoExtension(
            $registry,
            $translator ?? $this->createMock(TranslatorInterface::class),
            $boltConfig ?? $this->createMock(Config::class),
        );
    }

    /**
     * @param array<string, string> $fields
     */
    private function contentWithFields(array $fields): Content
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

        return $content;
    }

    private function contentWithFieldDefinition(string $fieldName, ContentType $definition): Content
    {
        $definitionFields = new Collection([$fieldName => $definition]);
        $contentDefinition = $this->createMock(ContentType::class);
        $contentDefinition->method('get')->willReturn($definitionFields);

        $content = $this->createMock(Content::class);
        $content->method('getDefinition')->willReturn($contentDefinition);

        return $content;
    }

    private function translatorReturning(string $value): TranslatorInterface
    {
        $translator = $this->createMock(TranslatorInterface::class);
        $translator->method('trans')->willReturn($value);

        return $translator;
    }

    // --- seoGetConfig ------------------------------------------------------

    public function testSeoGetConfigReturnsConfigAsArray(): void
    {
        $config = ['title_postfix' => false, 'fields' => ['title' => ['title']]];
        $extension = $this->makeExtension($config);

        self::assertSame($config, $extension->seoGetConfig());
    }

    // --- seoField ----------------------------------------------------------

    public function testSeoFieldReturnsNullWhenFieldNotConfigured(): void
    {
        $extension = $this->makeExtension(['fields' => []]);

        self::assertNull($extension->seoField($this->contentWithFields([]), 'title'));
    }

    public function testSeoFieldReturnsNullWhenContentLacksField(): void
    {
        $extension = $this->makeExtension(['fields' => ['title' => ['title', 'name']]]);

        self::assertNull($extension->seoField($this->contentWithFields([]), 'title'));
    }

    public function testSeoFieldReturnsFieldValue(): void
    {
        $extension = $this->makeExtension(['fields' => ['title' => ['title', 'name']]]);
        $content = $this->contentWithFields(['title' => 'My Title']);

        self::assertSame('My Title', $extension->seoField($content, 'title'));
    }

    public function testSeoFieldStripsTagsAndDecodesEntities(): void
    {
        $extension = $this->makeExtension(['fields' => ['description' => ['body']]]);
        $content = $this->contentWithFields(['body' => '<p>Fish &amp; <strong>chips</strong> &lt;3</p>']);

        self::assertSame('Fish & chips <3', $extension->seoField($content, 'description'));
    }

    public function testSeoFieldTurnsNonBreakingSpacesIntoNormalSpaces(): void
    {
        $extension = $this->makeExtension(['fields' => ['description' => ['body']]]);
        $content = $this->contentWithFields(['body' => "<p>Fish&nbsp;and\u{a0}chips</p>"]);

        self::assertSame('Fish and chips', $extension->seoField($content, 'description'));
    }

    public function testSeoFieldValueDescriptionUsesTheDecodedContentField(): void
    {
        $extension = $this->makeExtension(['fields' => ['description' => ['body']]]);
        $content = $this->contentWithFields(['body' => '<p>Fish &amp; chips</p>']);

        self::assertSame('Fish & chips', $extension->seoFieldValue($content, 'description'));
    }

    // --- seoFieldValue -----------------------------------------------------

    public function testSeoFieldValueTitleUsesFieldWhenPresent(): void
    {
        $extension = $this->makeExtension(['fields' => ['title' => ['title']]]);
        $content = $this->contentWithFields(['title' => 'Real Title']);

        self::assertSame('Real Title', $extension->seoFieldValue($content, 'title'));
    }

    public function testSeoFieldValueTitleFallsBackToTranslation(): void
    {
        $extension = $this->makeExtension(
            ['fields' => ['title' => ['title']]],
            $this->translatorReturning('Translated default')
        );

        self::assertSame('Translated default', $extension->seoFieldValue($this->contentWithFields([]), 'title'));
    }

    public function testSeoFieldValueSlugFallsBackToTranslation(): void
    {
        $extension = $this->makeExtension(
            ['fields' => ['slug' => ['slug']]],
            $this->translatorReturning('translated-slug')
        );

        self::assertSame('translated-slug', $extension->seoFieldValue($this->contentWithFields([]), 'slug'));
    }

    public function testSeoFieldValueDescriptionFallsBackToLoremDefault(): void
    {
        $extension = $this->makeExtension(['fields' => ['description' => ['description']]]);

        self::assertStringContainsString('Lorem ipsum', $extension->seoFieldValue($this->contentWithFields([]), 'description'));
    }

    public function testSeoFieldValuePostfixUsesSeparatorAndPostfix(): void
    {
        $extension = $this->makeExtension(['title_postfix' => 'Brand', 'title_separator' => '-']);

        self::assertSame(' - Brand', $extension->seoFieldValue($this->contentWithFields([]), 'postfix'));
    }

    public function testSeoFieldValuePostfixFallsBackToSitenameAndDefaultSeparator(): void
    {
        $boltConfig = $this->createMock(Config::class);
        $boltConfig->method('get')->willReturn('Acme');

        // Empty separator -> '|' (matching Seo::postfixTitle), empty postfix -> sitename.
        $extension = $this->makeExtension(
            ['title_postfix' => '', 'title_separator' => ''],
            boltConfig: $boltConfig
        );

        self::assertSame(' | Acme', $extension->seoFieldValue($this->contentWithFields([]), 'postfix'));
    }

    public function testSeoFieldValuePostfixDisabledReturnsEmptyString(): void
    {
        $extension = $this->makeExtension(['title_postfix' => false]);

        self::assertSame('', $extension->seoFieldValue($this->contentWithFields([]), 'postfix'));
    }

    public function testSeoFieldValuePostfixOmitsSeparatorWhenResolvedValueIsEmpty(): void
    {
        // No configured postfix and an empty sitename to fall back to: the resolved
        // postfix is empty, so the separator is omitted too (matching Seo::postfixTitle).
        $boltConfig = $this->createMock(Config::class);
        $boltConfig->method('get')->willReturn('');

        $extension = $this->makeExtension(
            ['title_postfix' => '', 'title_separator' => '|'],
            boltConfig: $boltConfig
        );

        self::assertSame('', $extension->seoFieldValue($this->contentWithFields([]), 'postfix'));
    }

    public function testSeoFieldValueUnknownFieldReturnsEmptyString(): void
    {
        $extension = $this->makeExtension(['fields' => []]);

        self::assertSame('', $extension->seoFieldValue($this->contentWithFields([]), 'nonexistent'));
    }

    // --- seoFieldDefinition ------------------------------------------------

    public function testSeoFieldDefinitionReturnsNullWhenFieldNotConfigured(): void
    {
        $extension = $this->makeExtension(['fields' => []]);

        self::assertNull($extension->seoFieldDefinition($this->contentWithFields([]), 'title'));
    }

    public function testSeoFieldDefinitionReturnsDefinition(): void
    {
        $definition = $this->createMock(ContentType::class);
        $extension = $this->makeExtension(['fields' => ['title' => ['title']]]);
        $content = $this->contentWithFieldDefinition('title', $definition);

        self::assertSame($definition, $extension->seoFieldDefinition($content, 'title'));
    }

    public function testSeoFieldDefinitionReturnsNullWhenDefinitionLacksField(): void
    {
        // Field is configured, but the content's definition has no matching field.
        $extension = $this->makeExtension(['fields' => ['title' => ['title', 'name']]]);
        $content = $this->contentWithFieldDefinition('other', $this->createMock(ContentType::class));

        self::assertNull($extension->seoFieldDefinition($content, 'title'));
    }

    public function testSeoFieldAcceptsASingleConfiguredFieldName(): void
    {
        $extension = $this->makeExtension(['fields' => ['title' => 'headline']]);

        self::assertSame(
            'Fish and chips',
            $extension->seoField($this->contentWithFields(['headline' => 'Fish and chips']), 'title')
        );
    }

    public function testSeoFieldSkipsConfiguredFieldNamesThatAreNotText(): void
    {
        $extension = $this->makeExtension(['fields' => ['title' => [5, null, 'headline']]]);

        self::assertSame(
            'Fish and chips',
            $extension->seoField($this->contentWithFields(['headline' => 'Fish and chips']), 'title')
        );
    }

    public function testSeoFieldValuePostfixLeavesOutASeparatorThatIsNotText(): void
    {
        // Like a missing `title_separator`; the admin preview collapses the spaces.
        $extension = $this->makeExtension(['title_postfix' => 'Acme', 'title_separator' => true]);

        self::assertSame('  Acme', $extension->seoFieldValue($this->contentWithFields([]), 'postfix'));
    }

    public function testSeoFieldDefinitionReturnsNullWhenDefinitionFieldsAreNotACollection(): void
    {
        $contentDefinition = $this->createMock(ContentType::class);
        $contentDefinition->method('get')->willReturn(null);
        $content = $this->createMock(Content::class);
        $content->method('getDefinition')->willReturn($contentDefinition);

        $extension = $this->makeExtension(['fields' => ['title' => ['title']]]);

        self::assertNull($extension->seoFieldDefinition($content, 'title'));
    }

    public function testSeoFieldDefinitionSkipsFieldsWithoutADefinition(): void
    {
        $definition = $this->createMock(ContentType::class);
        $definitionFields = new Collection(['title' => null, 'name' => $definition]);
        $contentDefinition = $this->createMock(ContentType::class);
        $contentDefinition->method('get')->willReturn($definitionFields);
        $content = $this->createMock(Content::class);
        $content->method('getDefinition')->willReturn($contentDefinition);

        $extension = $this->makeExtension(['fields' => ['title' => ['title', 'name']]]);

        self::assertSame($definition, $extension->seoFieldDefinition($content, 'title'));
    }

    // --- getFunctions ------------------------------------------------------

    public function testGetFunctionsRegistersExpectedTwigFunctions(): void
    {
        $extension = $this->makeExtension([]);

        $names = array_map(
            static fn (\Twig\TwigFunction $function): string => $function->getName(),
            $extension->getFunctions()
        );

        self::assertSame(
            ['seoGetConfig', 'seoFieldValue', 'seoFieldDefinition', 'seoField'],
            $names
        );
    }
}
