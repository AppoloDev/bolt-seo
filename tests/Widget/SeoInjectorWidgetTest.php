<?php

declare(strict_types=1);

namespace Appolo\BoltSeo\Tests\Widget;

use Appolo\BoltSeo\Widget\SeoInjectorWidget;
use Bolt\Extension\BaseExtension;
use Bolt\Extension\ExtensionInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

/**
 * The widget injects the SEO field's CSS and JS only when a record's edit form is
 * shown. These tests cover the cases where it injects nothing.
 */
class SeoInjectorWidgetTest extends TestCase
{
    /**
     * Without a Bolt extension to get the request from, inject nothing. Calling
     * getRequest() on it used to be an Error.
     */
    public function testInjectsNothingWithoutABoltExtension(): void
    {
        $widget = new SeoInjectorWidget();
        $widget->injectExtension($this->createMock(ExtensionInterface::class));

        self::assertNull($widget->run());
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function requestProvider(): array
    {
        return [
            'other route' => ['bolt_dashboard', 'GET'],
            'edit form submitted' => ['bolt_content_edit', 'POST'],
        ];
    }

    #[DataProvider('requestProvider')]
    public function testInjectsNothingOutsideTheEditForm(string $route, string $method): void
    {
        $request = Request::create('/bolt/edit/1', $method);
        $request->attributes->set('_route', $route);

        $extension = $this->createMock(BaseExtension::class);
        $extension->method('getRequest')->willReturn($request);

        $widget = new SeoInjectorWidget();
        $widget->injectExtension($extension);

        self::assertNull($widget->run());
    }
}
