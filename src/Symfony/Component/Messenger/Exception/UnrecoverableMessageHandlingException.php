<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Exception;


/**
 * Indicates an exception while handling a message that will continue to fail.
 *
 * If something goes wrong while a handling a message from a transport and the
 * message should not be requeued, a handler can throw this exception
 * and the message will not be requeued.
 *
 * @author Frederic Bouchery <frederic@bouchery.fr>
 *
 * @experimental in 4.3
 */
class UnrecoverableMessageHandlingException extends RuntimeException
{
}
