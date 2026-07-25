<?php

declare(strict_types=1);

namespace Appolo\BoltSeo;

use Appolo\BoltSeo\Seo\Seo;
use Appolo\BoltSeo\Widget\SeoInjectorWidget;
use Bolt\Extension\BaseExtension;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Contracts\Translation\TranslatorInterface;

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

        /** @var TranslatorInterface $translator */
        $translator = $this->getContainer()->get('translator');

        $seo = new Seo(
            $this->getTwig(),
            $this->getConfig(),
            $this->getBoltConfig(),
            $this->getRequest(),
            $translator
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
        $container = $this->getContainer();
        $projectDir = $container->getParameter('kernel.project_dir');
        $public = $container->getParameter('bolt.public_folder');

        if (! \is_string($projectDir) || ! \is_string($public)) {
            throw new \RuntimeException('The "kernel.project_dir" and "bolt.public_folder" container parameters must be strings.');
        }

        $source = \dirname(__DIR__) . '/assets/';
        $destination = $projectDir . '/' . $public . '/assets/';

        $filesystem = new Filesystem();
        $filesystem->mirror($source, $destination);
    }
}
