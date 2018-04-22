<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Transport\AmqpExt;

use Symfony\Component\Messenger\Exception\TransportException;
use Symfony\Component\Messenger\Transport\AmqpExt\Exception\RejectMessageExceptionInterface;
use Symfony\Component\Messenger\Transport\Receiver\ReceiverInterface;
use Symfony\Component\Messenger\Transport\Serialization\PhpSerializer;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;

/**
 * Symfony Messenger receiver to get messages from AMQP brokers using PHP's AMQP extension.
 *
 * @author Samuel Roze <samuel.roze@gmail.com>
 *
 * @experimental in 4.2
 */
class AmqpReceiver implements ReceiverInterface
{
    private $serializer;
    private $connection;
    private $logger;
    private $shouldStop;

    public function __construct(Connection $connection, SerializerInterface $serializer = null)
    {
        $this->connection = $connection;
        $this->serializer = $serializer ?? new PhpSerializer();
    }

    /**
     * {@inheritdoc}
     */
    public function receive(callable $handler): void
    {
        while (!$this->shouldStop) {
            $AMQPEnvelope = $this->connection->get();

            if (null === $AMQPEnvelope) {
                $handler(null);

                usleep($this->connection->getConnectionConfiguration()['loop_sleep'] ?? self::DEFAULT_LOOP_SLEEP_IN_MICRO_SECONDS);
                if (\function_exists('pcntl_signal_dispatch')) {
                    pcntl_signal_dispatch();
                }

                continue;
            }

            try {
                $handler($this->serializer->decode([
                    'body' => $AMQPEnvelope->getBody(),
                    'headers' => $AMQPEnvelope->getHeaders(),
                ]));

                $this->connection->ack($AMQPEnvelope);
            } catch (RejectMessageExceptionInterface $e) {
                try {
                    $this->connection->reject($AMQPEnvelope);
                } catch (\AMQPException $exception) {
                    throw new TransportException($exception->getMessage(), 0, $exception);
                }

                throw $e;
            } catch (\AMQPException $e) {
                throw new TransportException($e->getMessage(), 0, $e);
            } catch (\Throwable $e) {
                try {
                    $this->connection->nack($AMQPEnvelope, AMQP_REQUEUE);
                } catch (\AMQPException $exception) {
                    throw new TransportException($exception->getMessage(), 0, $exception);
                }

                    $retried = false;


                if (!$retried) {
                    $this->connection->nack($AMQPEnvelope, AMQP_REQUEUE);

                    throw $e;
                }

                // Acknowledge current message as another one as been requeued.
                $this->connection->ack($AMQPEnvelope);
            } finally {
                if (\function_exists('pcntl_signal_dispatch')) {
                    pcntl_signal_dispatch();
                }
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function stop(): void
    {
        $this->shouldStop = true;
    }
}
