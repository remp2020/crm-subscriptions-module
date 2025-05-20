<?php

namespace Crm\SubscriptionsModule\Models\Extension;

use Nette\DI\Container;

class ExtensionMethodFactory
{
    /** @var array<string> */
    private array $extensions = [];

    public function __construct(
        private Container $container,
    ) {
    }

    public function registerExtension(string $type, string $extensionMethod): void
    {
        if (isset($this->extensions[$type])) {
            throw new \Exception('Trying to register extension with code that is already used: ' . $type);
        }
        $this->extensions[$type] = $extensionMethod;
    }

    /**
     * @throws \Exception
     */
    public function getExtension(string $type): ExtensionInterface
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
