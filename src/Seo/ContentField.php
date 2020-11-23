<?php

declare(strict_types=1);

namespace Appolo\BoltSeo\Seo;

use Bolt\Configuration\Content\ContentType;
use Bolt\Entity\Content;
use Bolt\Entity\Field;

class ContentField
{
    public static function getFieldDefinition(Content $content, array $fields = []): ?ContentType
    {
        if (!isset($fields)) {
            return null;
        }

        $definitionFields = $content->getDefinition()->get('fields');
        foreach ($fields as $fieldName) {
            if ($definitionFields->has($fieldName)) {
                return $definitionFields->get($fieldName);
            }
        }

        return null;
    }

    public static function getField(Content $content, array $fields = []): ?Field
    {
        if (!isset($fields)) {
            return null;
        }

        foreach ($fields as $fieldName) {
            if ($content->hasField($fieldName)) {
                return $content->getField($fieldName);
            }
        }

        return null;
    }
}
