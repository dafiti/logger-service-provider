<?php

namespace Dafiti\Silex;

use Monolog\Handler;
use Monolog\Processor;
use Silex\Application;

class LoggerServiceProviderTest extends \PHPUnit_Framework_TestCase
{
    private $app;

    public function setUp()
    {
        $this->app = new Application();
        $this->app->register(new LoggerServiceProvider());

        parent::setUp();
    }

    public function tearDown()
    {
        $this->app = null;
    }

    /**
     * @covers Dafiti\Silex\LoggerServiceProvider::register
     */
    public function testShouldRegister()
    {
        $this->assertInstanceOf('\Dafiti\Silex\Log\Collection', $this->app['logger.manager']);
    }

    /**
     * @covers Dafiti\Silex\LoggerServiceProvider::register
     */
    public function testShouldRegisterWithParams()
    {
        $params = [
            'logger.log_folder' => 'data/logs/',
            'logger.level'      => 'debug'
        ];

        $app = new Application();

        $app->register(new LoggerServiceProvider(), $params);

        $this->assertInstanceOf('\Dafiti\Silex\Log\Collection', $app['logger.manager']);
        $this->assertEquals($params['logger.log_folder'], $app['logger.log_folder']);
        $this->assertEquals($params['logger.level'], $app['logger.level']);
    }

    /**
     * @covers Dafiti\Silex\LoggerServiceProvider::register
     */
    public function testShouldCreateLogger()
    {
        $logger = $this->app['logger.create']('process');
        $handlers = $logger->getHandlers();

        $this->assertInstanceOf('\Dafiti\Silex\Log\Logger', $logger);
        $this->assertContainsOnlyInstancesOf('\Monolog\Handler\StreamHandler', $handlers);
        $this->assertEquals($logger::DEBUG, $handlers[0]->getLevel());
    }

    /**
     * @covers Dafiti\Silex\LoggerServiceProvider::register
     */
    public function testShouldCreateLoggerWithAnotherHandler()
    {
        $logger = $this->app['logger.create']('process', 'debug', [
            new Handler\FirePHPHandler(),
            new Handler\ErrorLogHandler(Handler\ErrorLogHandler::OPERATING_SYSTEM)
        ]);

        $this->assertCount(2, $logger->getHandlers());
    }

    /**
     * @covers Dafiti\Silex\LoggerServiceProvider::register
     */
    public function testShouldCreateMultipleLoggers()
    {
        $app = new Application();

        $app->register(new LoggerServiceProvider(), [
            'logger.log_folder' => 'data/logs'
        ]);

        $processLogger = $app['logger.create']('process');
        $workerLogger  = $app['logger.create']('worker', 'warning');

        $this->assertCount(2, $app['logger.manager']);
        $this->assertTrue($app['logger.manager']->has('process'));
        $this->assertTrue($app['logger.manager']->has('worker'));
        $this->assertSame($processLogger, $app['logger.manager']->get('process'));
        $this->assertSame($workerLogger, $app['logger.manager']->get('worker'));
    }

    /**
     * @covers Dafiti\Silex\LoggerServiceProvider::register
     */
    public function testShouldCreateWithProcessor()
    {
        $worker = $this->app['logger.create']('worker', 'info', [], [
            new Processor\UidProcessor()
        ]);

        $this->assertCount(1, $worker->getProcessors());
    }

    /**
     * @covers Dafiti\Silex\LoggerServiceProvider::register
     *
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage Empty value is not allowed for loggers
     */
    public function testShouldThrowExceptionWhenFabricateWithWithoutLoggers()
    {
        $this->app['logger.factory']([]);
    }

    /**
     * @covers Dafiti\Silex\LoggerServiceProvider::register
     */
    public function testShouldFabricateLoggersWithoutHandlers()
    {
        $loggers = [
            'worker' => [
                'level' => 'info'
            ]
        ];

        $this->app['logger.factory']($loggers);

        $this->assertCount(1, $this->app['logger.manager']);
        $this->assertCount(1, $this->app['logger.manager']->worker->getHandlers());
        $this->assertInstanceOf('\Monolog\Handler\StreamHandler', $this->app['logger.manager']->worker->getHandlers()[0]);
    }

    /**
     * @covers Dafiti\Silex\LoggerServiceProvider::register
     */
    public function testShouldFabricateLoggersWithDefaulLevelInHandlersWhenIsNotDefined()
    {
        $loggers = [
            'worker' => [
                'level' => 'warning',
                'handlers' => [
                    [
                        'class' => '\Monolog\Handler\StreamHandler',
                        'params' => [
                            'stream'         => '/tmp/test.log',
                            'bubble'         => true,
                            'filePermission' => null
                        ]
                    ]
                ]
            ]
        ];

        $this->app['logger.factory']($loggers);

        $this->assertCount(1, $this->app['logger.manager']);
        $this->assertCount(1, $this->app['logger.manager']->worker->getHandlers());
        $this->assertInstanceOf('\Monolog\Handler\StreamHandler', $this->app['logger.manager']->worker->getHandlers()[0]);
    }

    /**
     * @covers Dafiti\Silex\LoggerServiceProvider::register
     */
    public function testShouldFabricateLoggersWithProcessors()
    {
        $loggers = [
            'worker' => [
                'level' => 'warning',
                'processors' => [
                    [
                        'class'  => '\Monolog\Processor\GitProcessor',
                        'params' => [
                            'level' => 'debug',
                        ]
                    ]
                ]
            ]
        ];

        $this->app['logger.factory']($loggers);

        $this->assertCount(1, $this->app['logger.manager']);
        $this->assertCount(1, $this->app['logger.manager']->worker->getProcessors());
        $this->assertInstanceOf('\Monolog\Processor\GitProcessor', $this->app['logger.manager']->worker->getProcessors()[0]);
    }

    /**
     * @covers Dafiti\Silex\LoggerServiceProvider::register
     */
    public function testShouldFabricateLoggers()
    {
        $loggers = [
            'worker' => [
                'level' => 'debug',
                'handlers' => [
                    [
                        'class' => '\Monolog\Handler\StreamHandler',
                        'params' => [
                            'stream'         => '/tmp/test.log',
                            'bubble'         => true,
                            'filePermission' => null
                        ],
                        'formatter' => [
                            'class' => '\Monolog\Formatter\JsonFormatter'
                        ]
                    ],
                    [
                        'class' => '\Monolog\Handler\SyslogHandler',
                        'params' => [
                            'ident'    => 'worker',
                            'facility' => LOG_USER
                        ]
                    ]
                ],
                'processors' => [
                    [
                        'class'  => '\Monolog\Processor\GitProcessor',
                        'params' => [
                            'level' => 'debug',
                        ]
                    ]
                ]
            ],
            'mail' => [
                'level' => 'debug',
                'handlers' => [
                    [
                        'class' => '\Monolog\Handler\StreamHandler',
                        'params' => [
                            'stream'         => '/tmp/test.log',
                            'bubble'         => true,
                            'filePermission' => null
                        ],
                        'formatter' => [
                            'class' => '\Monolog\Formatter\LogstashFormatter',
                            'params' => [
                                'applicationName' => 'test'
                            ]
                        ]
                    ]
                ]
            ]
        ];

        $this->app['logger.factory']($loggers);

        $this->assertCount(2, $this->app['logger.manager']);
        $this->assertCount(2, $this->app['logger.manager']->worker->getHandlers());
        $this->assertInstanceOf('\Monolog\Handler\StreamHandler', $this->app['logger.manager']->worker->getHandlers()[1]);

        $this->assertInstanceOf(
            '\Monolog\Formatter\JsonFormatter',
            $this->app['logger.manager']->worker->getHandlers()[1]->getFormatter()
        );

        $this->assertCount(1, $this->app['logger.manager']->worker->getProcessors());
        $this->assertInstanceOf('\Monolog\Processor\GitProcessor', $this->app['logger.manager']->worker->getProcessors()[0]);

        $this->assertCount(1, $this->app['logger.manager']->mail->getHandlers());
        $this->assertInstanceOf('\Monolog\Handler\StreamHandler', $this->app['logger.manager']->mail->getHandlers()[0]);

        $this->assertInstanceOf(
            '\Monolog\Formatter\LogstashFormatter',
            $this->app['logger.manager']->mail->getHandlers()[0]->getFormatter()
        );
    }
}
