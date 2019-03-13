<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger;

use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Messenger\Event\WorkerMessageHandledEvent;
use Symfony\Component\Messenger\Event\WorkerMessageHandlingEvent;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;
use Symfony\Component\Messenger\Transport\Receiver\ReceiverInterface;

/**
 * @author Samuel Roze <samuel.roze@gmail.com>
 *
 * @experimental in 4.2
 *
 * @final
 */
class Worker
{
    private $receiver;
    private $bus;

    private $eventDispatcher;

    public function __construct(ReceiverInterface $receiver, MessageBusInterface $bus, EventDispatcherInterface $eventDispatcher = null)
    {
        $this->receiver = $receiver;
        $this->bus = $bus;
        $this->eventDispatcher = $eventDispatcher;
    }

    /**
     * Receive the messages and dispatch them to the bus.
     */
    public function run()
    {
        if (\function_exists('pcntl_signal')) {
            pcntl_signal(SIGTERM, function () {
                $this->receiver->stop();
            });
        }

        $this->receiver->receive(function (?Envelope $envelope) {
            if (null === $envelope) {
                return;
            }

            $this->dispatchEvent(WorkerMessageHandlingEvent::class, new WorkerMessageHandlingEvent($envelope));

            try {
                $this->bus->dispatch($envelope->with(new ReceivedStamp()));

                $this->dispatchEvent(WorkerMessageHandledEvent::class, new WorkerMessageHandledEvent($envelope));
            } catch (\Throwable $e) {
                $this->dispatchEvent(WorkerMessageFailedEvent::class, new WorkerMessageFailedEvent($envelope, $e));
            }
        });
    }

    private function dispatchEvent(string $eventName, $event)
    {
        if (null === $this->eventDispatcher) {
            return;
        }

        $this->eventDispatcher->dispatch($eventName, $event);
    }
}
