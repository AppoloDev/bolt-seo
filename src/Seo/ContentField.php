<?php

declare(strict_types=1);

namespace Appolo\BoltSeo\Seo;

use Bolt\Configuration\Content\ContentType;
use Bolt\Entity\Content;
use Bolt\Entity\Field;
use Illuminate\Support\Collection;

class ContentField
{
    /**
     * @param array<int, string> $fields
     */
    public static function getFieldDefinition(Content $content, array $fields = []): ?ContentType
    {
        $definition = $content->getDefinition();
        if ($definition === null) {
            return null;
        }

        // A field name without a definition is skipped, so the next configured name
        // is tried. It used to end the lookup (null) or cause a TypeError.
        $definitionFields = $definition->get('fields');
        if (! $definitionFields instanceof Collection) {
            return null;
        }

        foreach ($fields as $fieldName) {
            $fieldDefinition = $definitionFields->get($fieldName);
            if ($fieldDefinition instanceof ContentType) {
                return $fieldDefinition;
            }
        }

        return null;
    }

    /**
     * @param array<int, string> $fields
     */
    public static function getField(Content $content, array $fields = []): ?Field
    {
        foreach ($fields as $fieldName) {
            if ($content->hasField($fieldName)) {
                return $content->getField($fieldName);
            }
        }

        return null;
    }
}
