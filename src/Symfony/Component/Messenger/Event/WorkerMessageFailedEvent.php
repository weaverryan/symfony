<?php

namespace Symfony\Component\Messenger\Event;

use Symfony\Component\EventDispatcher\Event;
use Symfony\Component\Messenger\Envelope;

class WorkerMessageFailedEvent extends Event
{
    private $envelope;

    private $throwable;

    public function __construct(Envelope $envelope, \Throwable $error)
    {
        $this->envelope = $envelope;
        $this->throwable = $error;
    }

    public function getEnvelope(): Envelope
    {
        return $this->envelope;
    }

    public function getThrowable(): \Throwable
    {
        return $this->throwable;
    }
}
