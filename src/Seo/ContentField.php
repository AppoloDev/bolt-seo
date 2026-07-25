<?php

declare(strict_types=1);

namespace Appolo\BoltSeo\Seo;

use Bolt\Configuration\Content\ContentType;
use Bolt\Entity\Content;
use Bolt\Entity\Field;

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

        $definitionFields = $definition->get('fields');
        foreach ($fields as $fieldName) {
            if ($definitionFields->has($fieldName)) {
                return $definitionFields->get($fieldName);
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
