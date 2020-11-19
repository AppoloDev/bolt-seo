<?php

declare(strict_types=1);

namespace Appolo\BoltSeo;

use Appolo\BoltSeo\Seo\Seo;
use Appolo\BoltSeo\Widget\SeoInjectorWidget;
use Bolt\Extension\BaseExtension;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\Filesystem\Filesystem;

class Extension extends BaseExtension
{
    public function getName(): string
    {
        return 'Bolt Seo';
    }

    public function initialize(): void
    {
        $this->addTwigNamespace('seo');
        // This Injector Widget is used to insert CSS and JS for a field type
        // Therefore it is only inserted once even if you have multiple fields of this field type
        $this->addWidget(new SeoInjectorWidget());

        $seo = new Seo(
            $this->getTwig(),
            $this->getConfig(),
            $this->getBoltConfig(),
            $this->getRequest(),
            $this->getContainer()->get('translator')
        );
        $this->getTwig()->addGlobal('seo', $seo);
    }

    /**
     * This function will copy all the files from /assets/ into the
     * /public/<extension-name>/ folder after it has been installed.
     *
     * If the user defines a different public directory the assets will
     * be copied to the custom public directory
     */
    public function install(): void
    {
        /** @var Container $container */
        $container = $this->getContainer();
        $projectDir = $container->getParameter('kernel.project_dir');
        $public = $container->getParameter('bolt.public_folder');

        $source = \dirname(__DIR__) . '/assets/';
        $destination = $projectDir . '/' . $public . '/assets/';

        $filesystem = new Filesystem();
        $filesystem->mirror($source, $destination);
    }
}
