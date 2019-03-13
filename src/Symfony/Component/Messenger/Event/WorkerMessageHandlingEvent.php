<?php

namespace Symfony\Component\Messenger\Event;

use Symfony\Component\EventDispatcher\Event;
use Symfony\Component\Messenger\Envelope;

class WorkerMessageHandlingEvent extends Event
{
    private $envelope;

    public function __construct(Envelope $envelope)
    {
        $this->envelope = $envelope;
    }

    public function getEnvelope(): Envelope
    {
        return $this->envelope;
    }
}
