<?php

namespace Crm\SubscriptionsModule\Extension;

use Nette\DI\Container;

class ExtensionMethodFactory
{
    /** @var array(ExtensionInterface) */
    private $extensions = [];

    private $container;

    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    public function registerExtension($type, string $extensionMethod)
    {
        if (isset($this->extensions[$type])) {
            throw new \Exception('Trying to register extension with code that is already used: ' . $type);
        }
        $this->extensions[$type] = $extensionMethod;
    }

    /**
     * @param string $type
     * @return ExtensionInterface
     * @throws \Exception
     */
    public function getExtension($type)
    {
        if (!isset($this->extensions[$type])) {
            throw new \Exception("Unknown extension type '{$type}'");
        }

        $extension = $this->container->getByType($this->extensions[$type]);
        if (!$extension) {
            throw new \Exception("Extension doesn't have any registered implementation: " . get_class($extension));
        }
        if (!$extension instanceof ExtensionInterface) {
            throw new \Exception("Accessed extension doesn't implement ExtensionInterface: " . get_class($extension));
        }
        return $extension;
    }
}
