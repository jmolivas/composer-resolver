<?php

declare(strict_types=1);

namespace Toflar\ComposerResolver;

/**
 * Class ApplicationTest
 *
 * @package Toflar\ComposerResolver
 */
class ApplicationTest extends \PHPUnit_Framework_TestCase
{
    public function testInstanceOf()
    {
        $app = new Application();
        $this->assertInstanceOf('Toflar\ComposerResolver\Application', $app);
    }

    /**
     * @dataProvider envProvider
     */
    public function testEnv($env, $key, $default, $type, $expected)
    {
        if (null !== $env) {
            putenv($env);
        }

        $app = new Application();
        $app['param'] = $app->env($key, $default, $type);
        $this->assertSame($expected, $app['param']);
    }

    public function envProvider()
    {
        return [
            'Test default' => [null, 'FOOBAR', 'test', 'string', 'test'],
            'Test regular' => ['FOOBAR=value', 'FOOBAR', 'test', 'string', 'value'],
            'Test cast' => ['FOOBAR=15', 'FOOBAR', 'test', 'integer', 15],
        ];
    }
}
