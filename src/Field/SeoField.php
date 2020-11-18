<?php

declare(strict_types=1);

namespace Appolo\BoltSeo\Field;

use Bolt\Entity\Field;
use Bolt\Entity\Field\Excerptable;
use Bolt\Entity\Field\RawPersistable;
use Bolt\Entity\FieldInterface;
use Doctrine\ORM\Mapping as ORM;
use Twig\Markup;

/**
 * @ORM\Entity
 */
class SeoField extends Field implements Excerptable, FieldInterface, RawPersistable
{
    public const TYPE = 'seo';

    /**
     * Override getTwigValue to render field as html
     */
    public function getTwigValue()
    {
        $value = $this->getParsedValue();

        return new Markup($value, 'UTF-8');
    }
}
