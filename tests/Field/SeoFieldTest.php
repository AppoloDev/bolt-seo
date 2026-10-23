<?php

declare(strict_types=1);

namespace Appolo\BoltSeo\Tests\Field;

use Appolo\BoltSeo\Field\SeoField;
use Bolt\Configuration\Content\FieldType;
use Bolt\Entity\Content;
use Bolt\Entity\Field;
use Bolt\Entity\Field\Excerptable;
use Bolt\Entity\Field\RawPersistable;
use Bolt\Entity\Field\TextField;
use Bolt\Entity\FieldInterface;
use Bolt\Utils\ContentHelper;
use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\TestCase;
use Twig\Markup;

/**
 * Smoke tests for the custom `seo` field type: it must declare the expected type
 * and contracts, render its stored JSON as Twig Markup (unescaped) rather than a
 * plain string, and stay out of the text Bolt shows to visitors.
 */
class SeoFieldTest extends TestCase
{
    public function testTypeAndContracts(): void
    {
        $field = new SeoField();

        self::assertSame('seo', SeoField::TYPE);
        self::assertInstanceOf(Field::class, $field);
        self::assertInstanceOf(FieldInterface::class, $field);
        self::assertInstanceOf(RawPersistable::class, $field);
    }

    public function testIsNotExcerptable(): void
    {
        // Bolt pastes every Excerptable field into `|excerpt` and the admin listings,
        // which showed the raw JSON ({"title":…,"description":…}) to visitors.
        self::assertNotInstanceOf(Excerptable::class, new SeoField());
    }

    public function testBoltDoesNotGuessTheSeoFieldAsTitle(): void
    {
        // A ContentType without a title-like field: Bolt falls back to the first
        // Excerptable field, which must be the text field, not the SEO JSON.
        $body = new TextField();
        $body->setName('body');

        $content = $this->createMock(Content::class);
        $content->method('hasField')->willReturn(false);
        $content->method('getFields')->willReturn(new ArrayCollection([$this->makeField('{"title":"Hello"}'), $body]));

        self::assertSame(['body'], ContentHelper::guessTitleFields($content));
    }

    public function testGetTwigValueReturnsMarkupWithStoredValue(): void
    {
        $field = $this->makeField('{"title":"Hello"}');

        $value = $field->getTwigValue();

        self::assertInstanceOf(Markup::class, $value);
        self::assertSame('{"title":"Hello"}', (string) $value);
    }

    private function makeField(string $value): SeoField
    {
        $field = new SeoField();
        $field->setDefinition('seo', new FieldType(['type' => 'seo']));
        $field->setName('seo');
        $field->setValue($value);

        return $field;
    }
}
