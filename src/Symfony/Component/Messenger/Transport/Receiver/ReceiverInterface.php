<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Transport\Receiver;

use Symfony\Component\Messenger\Exception\TransportException;

/**
 * @author Samuel Roze <samuel.roze@gmail.com>
 *
 * @experimental in 4.2
 */
interface ReceiverInterface
{
    /**
     * Receive some messages to the given handler.
     *
     * The handler will have, as argument, the received {@link \Symfony\Component\Messenger\Envelope}
     * Note that this envelope can be `null` if the timeout to receive something has expired.
     * containing the message and an instance of @link \Symfony\Component\Messenger\Transport\QueuedMessageMetadata}.
     * The handler should not throw an exception.
     *
     * @throws TransportException If there is an issue communicating with the transport
     */
    public function receive(callable $handler): void;

    /**
     * Acknowlege that the message was handled (i.e. remove from queue).
     *
     * @param mixed $message A message as understood by the transport
     * @throws TransportException If there is an issue communicating with the transport
     */
    public function acknowledge($message): void;

    /**
     * Called when the handling of a message has failed.
     *
     * @param mixed $message A message as understood by the transport
     * @throws TransportException If there is an issue communicating with the transport
     */
    public function reject($message): void;

    /**
     * Called when the handling of a message failed but should be retried.
     *
     * @param mixed $message A message as understood by the transport
     * @throws TransportException If there is an issue communicating with the transport
     */
    public function retry($message): void;

    /**
     * Stop receiving some messages.
     */
    public function stop(): void;
}
