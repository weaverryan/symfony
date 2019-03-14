<?php

namespace Symfony\Component\Messenger\Transport;

class QueuedMessageMetadata
{
    private $messageId;

    private $numberOfRetries;

    /**
     * @param mixed $messageId A message id that's understood by the transport
     */
    public function __construct($messageId, int $numberOfRetries)
    {
        $this->messageId = $messageId;
        $this->numberOfRetries = $numberOfRetries;
    }

    public function getMessageId()
    {
        return $this->messageId;
    }

    public function getNumberOfRetries(): int
    {
        return $this->numberOfRetries;
    }
}
