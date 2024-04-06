<?php
/**
 * This file is part of the ZBateson\MailMimeParser project.
 *
 * @license http://opensource.org/licenses/bsd-license.php BSD
 */

namespace ZBateson\MailMimeParser\Header\Consumer;

use ZBateson\MailMimeParser\Header\Part\MimeTokenPartFactory;

/**
 * GenericConsumerMimeLiteralPartService uses a MimeTokenPartFactory instead
 * of a HeaderPartFactory.
 *
 * @author Zaahid Bateson
 */
class GenericConsumerMimeLiteralPartService extends GenericConsumerService
{
    public function __construct(
        MimeTokenPartFactory $partFactory,
        CommentConsumerService $commentConsumerService,
        QuotedStringConsumerService $quotedStringConsumerService
    ) {
        parent::__construct(
            $partFactory,
            $commentConsumerService,
            $quotedStringConsumerService
        );
    }
}
