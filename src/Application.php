<?php

namespace Toflar\ComposerResolver;

/**
 * Class Application
 *
 * @package Toflar\ComposerResolver
 */
class Application extends \Silex\Application
{
    /**
     * @param string     $key
     * @param mixed|null $default
     * @param string     $type
     *
     * @return \Closure
     */
    public function env($key, $default = null, $type = 'string')
    {
        return function () use ($key, $default, $type) {
            $envValue = getenv($key);

            if (false !== $envValue && '' !== $envValue) {
                settype($envValue, $type);
                return $envValue;
            }

            return $default;
        };
    }
}
