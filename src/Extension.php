<?php

namespace Appolo\BoltSeo;

use Appolo\BoltSeo\Widget\SeoInjectorWidget;
use Bolt\Extension\BaseExtension;
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
        $projectDir = $this->getContainer()->getParameter('kernel.project_dir');
        $public = $this->getContainer()->getParameter('bolt.public_folder');

        $source = dirname(__DIR__) . '/assets/';
        $destination = $projectDir . '/' . $public . '/assets/';

        $filesystem = new Filesystem();
        $filesystem->mirror($source, $destination);
    }
}
