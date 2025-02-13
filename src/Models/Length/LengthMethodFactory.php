<?php

namespace Crm\SubscriptionsModule\Models\Length;

class LengthMethodFactory
{
    /** @var LengthMethodInterface[] */
    private $lengthMethods = [];

    public function registerExtension($type, LengthMethodInterface $lengthMethod)
    {
        $this->lengthMethods[$type] = $lengthMethod;
    }

    /**
     * @param string $type
     * @return LengthMethodInterface
     * @throws \Exception
     */
    public function getExtension($type)
    {
        if (isset($this->lengthMethods[$type])) {
            return $this->lengthMethods[$type];
        }
        throw new \Exception("Unknown length method type '{$type}'");
    }
}
