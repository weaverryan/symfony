<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Tests\Transport\AmqpExt;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Exception\InvalidArgumentException;
use Symfony\Component\Messenger\Transport\AmqpExt\AmqpFactory;
use Symfony\Component\Messenger\Transport\AmqpExt\Connection;

/**
 * @requires extension amqp
 */
class ConnectionTest extends TestCase
{
    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage The given AMQP DSN "amqp://" is invalid.
     */
    public function testItCannotBeConstructedWithAWrongDsn()
    {
        Connection::fromDsn('amqp://');
    }

    public function testItGetsParametersFromTheDsn()
    {
        $this->assertEquals(
            new Connection([
                'host' => 'localhost',
                'port' => 5672,
                'vhost' => '/',
            ], [
                'name' => 'messages',
            ], [
                'name' => 'messages',
            ]),
            Connection::fromDsn('amqp://localhost/%2f/messages')
        );
    }

    public function testOverrideOptionsViaQueryParameters()
    {
        $this->assertEquals(
            new Connection([
                'host' => 'redis',
                'port' => 1234,
                'vhost' => '/',
                'login' => 'guest',
                'password' => 'password',
            ], [
                'name' => 'exchangeName',
            ], [
                'name' => 'queue',
            ]),
            Connection::fromDsn('amqp://guest:password@redis:1234/%2f/queue?exchange[name]=exchangeName')
        );
    }

    public function testOptionsAreTakenIntoAccountAndOverwrittenByDsn()
    {
        $this->assertEquals(
            new Connection([
                'host' => 'redis',
                'port' => 1234,
                'vhost' => '/',
                'login' => 'guest',
                'password' => 'password',
                'persistent' => 'true',
            ], [
                'name' => 'exchangeName',
            ], [
                'name' => 'queueName',
            ]),
            Connection::fromDsn('amqp://guest:password@redis:1234/%2f/queue?exchange[name]=exchangeName&queue[name]=queueName', [
                'persistent' => 'true',
                'exchange' => ['name' => 'toBeOverwritten'],
            ])
        );
    }

    public function testSetsParametersOnTheQueueAndExchange()
    {
        $factory = new TestAmqpFactory(
            $amqpConnection = $this->getMockBuilder(\AMQPConnection::class)->disableOriginalConstructor()->getMock(),
            $amqpChannel = $this->getMockBuilder(\AMQPChannel::class)->disableOriginalConstructor()->getMock(),
            $amqpQueue = $this->getMockBuilder(\AMQPQueue::class)->disableOriginalConstructor()->getMock(),
            $amqpExchange = $this->getMockBuilder(\AMQPExchange::class)->disableOriginalConstructor()->getMock()
        );

        $amqpQueue->expects($this->once())->method('setArguments')->with([
            'x-dead-letter-exchange' => 'dead-exchange',
            'x-delay' => 100,
            'x-expires' => 150,
            'x-max-length' => 200,
            'x-max-length-bytes' => 300,
            'x-max-priority' => 4,
            'x-message-ttl' => 100,
        ]);

        $amqpExchange->expects($this->once())->method('setArguments')->with([
            'alternate-exchange' => 'alternate',
        ]);

        $dsn = 'amqp://localhost/%2f/messages?'.
            'queue[arguments][x-dead-letter-exchange]=dead-exchange&'.
            'queue[arguments][x-message-ttl]=100&'.
            'queue[arguments][x-delay]=100&'.
            'queue[arguments][x-expires]=150&'
        ;
        $connection = Connection::fromDsn($dsn, [
            'queue' => [
                'arguments' => [
                    'x-max-length' => '200',
                    'x-max-length-bytes' => '300',
                    'x-max-priority' => '4',
                ],
            ],
            'exchange' => [
                'arguments' => [
                    'alternate-exchange' => 'alternate',
                ],
            ],
        ], true, $factory);
        $connection->publish('body');
    }

    public function invalidQueueArgumentsDataProvider(): iterable
    {
        $baseDsn = 'amqp://localhost/%2f/messages';
        yield [$baseDsn.'?queue[arguments][x-delay]=not-a-number', []];
        yield [$baseDsn.'?queue[arguments][x-expires]=not-a-number', []];
        yield [$baseDsn.'?queue[arguments][x-max-length]=not-a-number', []];
        yield [$baseDsn.'?queue[arguments][x-max-length-bytes]=not-a-number', []];
        yield [$baseDsn.'?queue[arguments][x-max-priority]=not-a-number', []];
        yield [$baseDsn.'?queue[arguments][x-message-ttl]=not-a-number', []];

        // Ensure the exception is thrown when the arguments are passed via the array options
        yield [$baseDsn, ['queue' => ['arguments' => ['x-delay' => 'not-a-number']]]];
        yield [$baseDsn, ['queue' => ['arguments' => ['x-expires' => 'not-a-number']]]];
        yield [$baseDsn, ['queue' => ['arguments' => ['x-max-length' => 'not-a-number']]]];
        yield [$baseDsn, ['queue' => ['arguments' => ['x-max-length-bytes' => 'not-a-number']]]];
        yield [$baseDsn, ['queue' => ['arguments' => ['x-max-priority' => 'not-a-number']]]];
        yield [$baseDsn, ['queue' => ['arguments' => ['x-message-ttl' => 'not-a-number']]]];
    }

    /**
     * @dataProvider invalidQueueArgumentsDataProvider
     */
    public function testFromDsnWithInvalidValueOnQueueArguments(string $dsn, array $options)
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Integer expected for queue argument');

        Connection::fromDsn($dsn, $options);
    }

    public function testItUsesANormalConnectionByDefault()
    {
        $factory = new TestAmqpFactory(
            $amqpConnection = $this->getMockBuilder(\AMQPConnection::class)->disableOriginalConstructor()->getMock(),
            $amqpChannel = $this->getMockBuilder(\AMQPChannel::class)->disableOriginalConstructor()->getMock(),
            $amqpQueue = $this->getMockBuilder(\AMQPQueue::class)->disableOriginalConstructor()->getMock(),
            $amqpExchange = $this->getMockBuilder(\AMQPExchange::class)->disableOriginalConstructor()->getMock()
        );

        $amqpConnection->expects($this->once())->method('connect');

        $connection = Connection::fromDsn('amqp://localhost/%2f/messages', [], false, $factory);
        $connection->publish('body');
    }

    public function testItAllowsToUseAPersistentConnection()
    {
        $factory = new TestAmqpFactory(
            $amqpConnection = $this->getMockBuilder(\AMQPConnection::class)->disableOriginalConstructor()->getMock(),
            $amqpChannel = $this->getMockBuilder(\AMQPChannel::class)->disableOriginalConstructor()->getMock(),
            $amqpQueue = $this->getMockBuilder(\AMQPQueue::class)->disableOriginalConstructor()->getMock(),
            $amqpExchange = $this->getMockBuilder(\AMQPExchange::class)->disableOriginalConstructor()->getMock()
        );

        $amqpConnection->expects($this->once())->method('pconnect');

        $connection = Connection::fromDsn('amqp://localhost/%2f/messages?persistent=true', [], false, $factory);
        $connection->publish('body');
    }

    public function testItSetupsTheConnectionWhenDebug()
    {
        $factory = new TestAmqpFactory(
            $amqpConnection = $this->getMockBuilder(\AMQPConnection::class)->disableOriginalConstructor()->getMock(),
            $amqpChannel = $this->getMockBuilder(\AMQPChannel::class)->disableOriginalConstructor()->getMock(),
            $amqpQueue = $this->getMockBuilder(\AMQPQueue::class)->disableOriginalConstructor()->getMock(),
            $amqpExchange = $this->getMockBuilder(\AMQPExchange::class)->disableOriginalConstructor()->getMock()
        );

        $amqpExchange->method('getName')->willReturn('exchange_name');
        $amqpExchange->expects($this->once())->method('declareExchange');
        $amqpQueue->expects($this->once())->method('declareQueue');
        $amqpQueue->expects($this->once())->method('bind')->with('exchange_name', 'my_key');

        $connection = Connection::fromDsn('amqp://localhost/%2f/messages?queue[routing_key]=my_key', [], true, $factory);
        $connection->publish('body');
    }

    public function testItCanDisableTheSetup()
    {
        $factory = new TestAmqpFactory(
            $amqpConnection = $this->getMockBuilder(\AMQPConnection::class)->disableOriginalConstructor()->getMock(),
            $amqpChannel = $this->getMockBuilder(\AMQPChannel::class)->disableOriginalConstructor()->getMock(),
            $amqpQueue = $this->getMockBuilder(\AMQPQueue::class)->disableOriginalConstructor()->getMock(),
            $amqpExchange = $this->getMockBuilder(\AMQPExchange::class)->disableOriginalConstructor()->getMock()
        );

        $amqpExchange->method('getName')->willReturn('exchange_name');
        $amqpExchange->expects($this->never())->method('declareExchange');
        $amqpQueue->expects($this->never())->method('declareQueue');
        $amqpQueue->expects($this->never())->method('bind');

        $connection = Connection::fromDsn('amqp://localhost/%2f/messages?queue[routing_key]=my_key', ['auto-setup' => 'false'], true, $factory);
        $connection->publish('body');

        $connection = Connection::fromDsn('amqp://localhost/%2f/messages?queue[routing_key]=my_key', ['auto-setup' => false], true, $factory);
        $connection->publish('body');

        $connection = Connection::fromDsn('amqp://localhost/%2f/messages?queue[routing_key]=my_key&auto-setup=false', [], true, $factory);
        $connection->publish('body');
    }

    public function testPublishWithQueueOptions()
    {
        $factory = new TestAmqpFactory(
            $amqpConnection = $this->createMock(\AMQPConnection::class),
            $amqpChannel = $this->createMock(\AMQPChannel::class),
            $amqpQueue = $this->createMock(\AMQPQueue::class),
            $amqpExchange = $this->createMock(\AMQPExchange::class)
        );
        $headers = [
            'type' => '*',
        ];
        $amqpExchange->expects($this->once())->method('publish')
            ->with('body', null, 1, ['delivery_mode' => 2, 'headers' => ['token' => 'uuid', 'type' => '*']]);

        $connection = Connection::fromDsn('amqp://localhost/%2f/messages?queue[attributes][delivery_mode]=2&queue[attributes][headers][token]=uuid&queue[flags]=1', [], true, $factory);
        $connection->publish('body', $headers);
    }

    public function testItRetriesTheMessage()
    {
        $amqpConnection = $this->getMockBuilder(\AMQPConnection::class)->disableOriginalConstructor()->getMock();
        $amqpChannel = $this->getMockBuilder(\AMQPChannel::class)->disableOriginalConstructor()->getMock();
        $retryQueue = $this->getMockBuilder(\AMQPQueue::class)->disableOriginalConstructor()->getMock();

        $factory = $this->getMockBuilder(AmqpFactory::class)->getMock();
        $factory->method('createConnection')->willReturn($amqpConnection);
        $factory->method('createChannel')->willReturn($amqpChannel);
        $factory->method('createQueue')->willReturn($retryQueue);
        $factory->method('createExchange')->will($this->onConsecutiveCalls(
            $retryExchange = $this->getMockBuilder(\AMQPExchange::class)->disableOriginalConstructor()->getMock(),
            $amqpExchange = $this->getMockBuilder(\AMQPExchange::class)->disableOriginalConstructor()->getMock()
        ));

        $amqpExchange->expects($this->once())->method('setName')->with('messages');
        $amqpExchange->method('getName')->willReturn('messages');

        $retryExchange->expects($this->once())->method('setName')->with('retry');
        $retryExchange->expects($this->once())->method('declareExchange');
        $retryExchange->method('getName')->willReturn('retry');

        $retryQueue->expects($this->once())->method('setName')->with('retry_queue_1');
        $retryQueue->expects($this->once())->method('setArguments')->with(array(
            'x-message-ttl' => 10000,
            'x-dead-letter-exchange' => 'messages',
        ));

        $retryQueue->expects($this->once())->method('declareQueue');
        $retryQueue->expects($this->once())->method('bind')->with('retry', 'attempt_1');

        $envelope = $this->getMockBuilder(\AMQPEnvelope::class)->getMock();
        $envelope->method('getHeader')->with('symfony-messenger-attempts')->willReturn(false);
        $envelope->method('getHeaders')->willReturn(array('x-some-headers' => 'foo'));
        $envelope->method('getBody')->willReturn('{}');

        $retryExchange->expects($this->once())->method('publish')->with('{}', 'attempt_1', AMQP_NOPARAM, array('headers' => array('x-some-headers' => 'foo', 'symfony-messenger-attempts' => 1)));

        $connection = Connection::fromDsn('amqp://localhost/%2f/messages', array('retry' => array('attempts' => 3)), false, $factory);
        $connection->publishForRetry($envelope);
    }

    public function testItRetriesTheMessageWithADifferentRoutingKeyAndTTLs()
    {
        $amqpConnection = $this->getMockBuilder(\AMQPConnection::class)->disableOriginalConstructor()->getMock();
        $amqpChannel = $this->getMockBuilder(\AMQPChannel::class)->disableOriginalConstructor()->getMock();
        $retryQueue = $this->getMockBuilder(\AMQPQueue::class)->disableOriginalConstructor()->getMock();

        $factory = $this->getMockBuilder(AmqpFactory::class)->getMock();
        $factory->method('createConnection')->willReturn($amqpConnection);
        $factory->method('createChannel')->willReturn($amqpChannel);
        $factory->method('createQueue')->willReturn($retryQueue);
        $factory->method('createExchange')->will($this->onConsecutiveCalls(
            $retryExchange = $this->getMockBuilder(\AMQPExchange::class)->disableOriginalConstructor()->getMock(),
            $amqpExchange = $this->getMockBuilder(\AMQPExchange::class)->disableOriginalConstructor()->getMock()
        ));

        $amqpExchange->expects($this->once())->method('setName')->with('messages');
        $amqpExchange->method('getName')->willReturn('messages');

        $retryExchange->expects($this->once())->method('setName')->with('retry');
        $retryExchange->expects($this->once())->method('declareExchange');
        $retryExchange->method('getName')->willReturn('retry');

        $connectionOptions = array(
            'retry' => array(
                'attempts' => 3,
                'dead_routing_key' => 'my_dead_routing_key',
                'ttl' => array(30000, 60000, 120000),
            ),
        );

        $connection = Connection::fromDsn('amqp://localhost/%2f/messages', $connectionOptions, false, $factory);

        $messageRetriedTwice = $this->getMockBuilder(\AMQPEnvelope::class)->getMock();
        $messageRetriedTwice->method('getHeader')->with('symfony-messenger-attempts')->willReturn('2');
        $messageRetriedTwice->method('getHeaders')->willReturn(array('symfony-messenger-attempts' => '2'));
        $messageRetriedTwice->method('getBody')->willReturn('{}');

        $retryQueue->expects($this->once())->method('setName')->with('retry_queue_3');
        $retryQueue->expects($this->once())->method('setArguments')->with(array(
            'x-message-ttl' => 120000,
            'x-dead-letter-exchange' => 'messages',
        ));

        $retryQueue->expects($this->once())->method('declareQueue');
        $retryQueue->expects($this->once())->method('bind')->with('retry', 'attempt_3');

        $retryExchange->expects($this->once())->method('publish')->with('{}', 'attempt_3', AMQP_NOPARAM, array('headers' => array('symfony-messenger-attempts' => 3)));
        $connection->publishForRetry($messageRetriedTwice);
    }
}

class TestAmqpFactory extends AmqpFactory
{
    private $connection;
    private $channel;
    private $queue;
    private $exchange;

    public function __construct(\AMQPConnection $connection, \AMQPChannel $channel, \AMQPQueue $queue, \AMQPExchange $exchange)
    {
        $this->connection = $connection;
        $this->channel = $channel;
        $this->queue = $queue;
        $this->exchange = $exchange;
    }

    public function createConnection(array $credentials): \AMQPConnection
    {
        return $this->connection;
    }

    public function createChannel(\AMQPConnection $connection): \AMQPChannel
    {
        return $this->channel;
    }

    public function createQueue(\AMQPChannel $channel): \AMQPQueue
    {
        return $this->queue;
    }

    public function createExchange(\AMQPChannel $channel): \AMQPExchange
    {
        return $this->exchange;
    }
}
