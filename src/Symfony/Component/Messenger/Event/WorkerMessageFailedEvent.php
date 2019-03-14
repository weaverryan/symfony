<?php

namespace Symfony\Component\Messenger\Event;

use Symfony\Component\EventDispatcher\Event;
use Symfony\Component\Messenger\Envelope;

class WorkerMessageFailedEvent extends Event
{
    private $envelope;

    private $throwable;

    private $wasRequeued;

    public function __construct(Envelope $envelope, \Throwable $error, bool $wasRequeued)
    {
        $this->envelope = $envelope;
        $this->throwable = $error;
        $this->wasRequeued = $wasRequeued;
    }

    public function getEnvelope(): Envelope
    {
        return $this->envelope;
    }

    public function getThrowable(): \Throwable
    {
        return $this->throwable;
    }

    public function wasRequeued(): bool
    {
        return $this->wasRequeued;
    }
}
