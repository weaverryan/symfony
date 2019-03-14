<?php

namespace Symfony\Component\Messenger\Transport;

class QueuedMessageMetadata
{
    private $message;

    private $numberOfTimesRetried;

    /**
     * @param mixed $message A "message" that's understood by the transport
     */
    public function __construct($message, int $numberOfRetries)
    {
        $this->message = $message;
        $this->numberOfTimesRetried = $numberOfRetries;
    }

    public function getMessage()
    {
        return $this->message;
    }

    public function getNumberOfTimesRetried(): int
    {
        return $this->numberOfTimesRetried;
    }
}
