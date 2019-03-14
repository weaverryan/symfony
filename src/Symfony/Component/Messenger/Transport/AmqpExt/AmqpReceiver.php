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
use Symfony\Component\Messenger\Transport\QueuedMessageMetadata;
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
    private const ATTEMPT_COUNT_HEADER_NAME = 'symfony-messenger-attempts';

    private $serializer;
    private $connection;
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
            try {
                $AMQPEnvelope = $this->connection->get();
            } catch (\AMQPException $exception) {
                throw new TransportException($exception->getMessage(), 0, $exception);
            }

            if (null === $AMQPEnvelope) {
                $handler(null, null);

                usleep($this->connection->getConnectionCredentials()['loop_sleep'] ?? 200000);
                if (\function_exists('pcntl_signal_dispatch')) {
                    pcntl_signal_dispatch();
                }

                continue;
            }

            $handler($this->serializer->decode([
                'body' => $AMQPEnvelope->getBody(),
                'headers' => $AMQPEnvelope->getHeaders(),
            ]), new QueuedMessageMetadata(
                $AMQPEnvelope,
                (int) $AMQPEnvelope->getHeader(self::ATTEMPT_COUNT_HEADER_NAME) ?: 0
            ));

            if (\function_exists('pcntl_signal_dispatch')) {
                pcntl_signal_dispatch();
            }
        }
    }

    public function acknowledge($message): void
    {
        try {
            $this->connection->ack($message);
        } catch (\AMQPException $exception) {
            throw new TransportException($exception->getMessage(), 0, $exception);
        }
    }

    public function reject($message): void
    {
        try {
            $this->connection->nack($message, AMQP_NOPARAM);
        } catch (\AMQPException $exception) {
            throw new TransportException($exception->getMessage(), 0, $exception);
        }
    }

    public function retry($message, int $retryDelay): void
    {
        if (!$message instanceof \AMQPEnvelope) {
            throw new \InvalidArgumentException('Invalid argument: expected AMQPEnvelope');
        }

        $headers = $message->getHeaders();
        // increment the number of attempts
        $attemptNumber = ((int) $message->getHeader(self::ATTEMPT_COUNT_HEADER_NAME) ?: 0) + 1;
        $headers[self::ATTEMPT_COUNT_HEADER_NAME] = $attemptNumber;

        // TODO - use retryDelay

        try {
            $this->connection->publish(
                $message->getBody(),
                $headers
            );

            // Acknowledge current message as another one as been requeued.
            $this->connection->ack($message);
        } catch (\AMQPException $exception) {
            throw new TransportException($exception->getMessage(), 0, $exception);
        }
    }

    public function stop(): void
    {
        $this->shouldStop = true;
    }
}
