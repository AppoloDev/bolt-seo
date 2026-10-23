<?php

declare(strict_types=1);

namespace Appolo\BoltSeo\Field;

use Bolt\Entity\Field;
use Bolt\Entity\Field\RawPersistable;
use Bolt\Entity\FieldInterface;
use Doctrine\ORM\Mapping as ORM;
use Twig\Markup;

/**
 * Not Excerptable: the stored value is JSON metadata, not text a visitor should read.
 * Bolt pastes every Excerptable field into excerpts (`|excerpt`, admin listings) and
 * may pick one as the record title when the ContentType has no title-like field.
 */
#[ORM\Entity]
class SeoField extends Field implements FieldInterface, RawPersistable
{
    public const TYPE = 'seo';

    /**
     * Override getTwigValue to render field as html
     */
    public function getTwigValue(): Markup
    {
        $value = $this->getParsedValue();

        return new Markup($value, 'UTF-8');
    }
}
