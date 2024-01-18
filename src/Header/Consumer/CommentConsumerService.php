<?php
/**
 * This file is part of the ZBateson\MailMimeParser project.
 *
 * @license http://opensource.org/licenses/bsd-license.php BSD
 */

namespace ZBateson\MailMimeParser\Header\Consumer;

use ZBateson\MailMimeParser\Header\Part\MimeLiteralPartFactory;
use ZBateson\MailMimeParser\Header\IHeaderPart;
use ZBateson\MailMimeParser\Header\Part\CommentPart;
use ZBateson\MailMimeParser\Header\Part\LiteralPart;
use Iterator;

/**
 * Consumes all tokens within parentheses as comments.
 *
 * Parenthetical comments in mime-headers can be nested within one another.  The
 * outer-level continues after an inner-comment ends.  Additionally,
 * quoted-literals may exist with comments as well meaning a parenthesis inside
 * a quoted string would not begin or end a comment section.
 *
 * In order to satisfy these specifications, CommentConsumerService inherits
 * from GenericConsumerService which defines CommentConsumerService and
 * QuotedStringConsumerService as sub-consumers.
 *
 * Examples:
 * X-Mime-Header: Some value (comment)
 * X-Mime-Header: Some value (comment (nested comment) still in comment)
 * X-Mime-Header: Some value (comment "and part of original ) comment" -
 *      still a comment)
 *
 * @author Zaahid Bateson
 */
class CommentConsumerService extends GenericConsumerService
{
    public function __construct(
        MimeLiteralPartFactory $partFactory,
        QuotedStringConsumerService $quotedStringConsumerService
    ) {
        parent::__construct(
            $partFactory,
            $this,
            $quotedStringConsumerService
        );
    }

    /**
     * Returns patterns matching open and close parenthesis characters
     * as separators.
     *
     * @return string[] the patterns
     */
    protected function getTokenSeparators() : array
    {
        return ['\(', '\)'];
    }

    /**
     * Returns true if the token is an open parenthesis character, '('.
     */
    protected function isStartToken(string $token) : bool
    {
        return ($token === '(');
    }

    /**
     * Returns true if the token is a close parenthesis character, ')'.
     */
    protected function isEndToken(string $token) : bool
    {
        return ($token === ')');
    }

    /**
     * Instantiates and returns Part\Token objects.
     *
     * Tokens from this and sub-consumers are combined into a Part\CommentPart
     * in processParts.
     */
    protected function getPartForToken(string $token, bool $isLiteral) : ?IHeaderPart
    {
        return $this->partFactory->newToken($token);
    }

    /**
     * Calls $tokens->next() and returns.
     *
     * The default implementation checks if the current token is an end token,
     * and will not advance past it.  Because a comment part of a header can be
     * nested, its implementation must advance past its own 'end' token.
     *
     * @return static
     */
    protected function advanceToNextToken(Iterator $tokens, bool $isStartToken) : AbstractConsumerService
    {
        $tokens->next();
        return $this;
    }

    /**
     * Post processing involves creating a single Part\CommentPart out of
     * generated parts from tokens.  The Part\CommentPart is returned in an
     * array.
     *
     * @param IHeaderPart[] $parts
     * @return IHeaderPart[]
     */
    protected function processParts(array $parts) : array
    {
        $comment = '';
        foreach ($parts as $part) {
            // order is important here - CommentPart extends LiteralPart
            if ($part instanceof CommentPart) {
                $comment .= '(' . $part->getComment() . ')';
            } elseif ($part instanceof LiteralPart) {
                $comment .= '"' . \str_replace('(["\\])', '\$1', $part->getValue()) . '"';
            } else {
                $comment .= $part->getValue();
            }
        }
        return [$this->partFactory->newCommentPart($comment)];
    }
}
