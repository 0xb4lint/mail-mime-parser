<?php
/**
 * This file is part of the ZBateson\MailMimeParser project.
 *
 * @license http://opensource.org/licenses/bsd-license.php BSD
 */

namespace ZBateson\MailMimeParser\Header\Consumer\Received;

use ZBateson\MailMimeParser\Header\Consumer\AbstractGenericConsumerService;
use ZBateson\MailMimeParser\Header\Consumer\CommentConsumerService;
use ZBateson\MailMimeParser\Header\Part\CommentPart;
use ZBateson\MailMimeParser\Header\Part\HeaderPartFactory;

/**
 * Consumes simple literal strings for parts of a Received header.
 *
 * Starts consuming when the initialized $partName string is located, for
 * instance when initialized with "FROM", will start consuming on " FROM" or
 * "FROM ".
 *
 * The consumer ends when any possible "Received" header part is found, namely
 * on one of the following tokens: from, by, via, with, id, for, or when the
 * start token for the date stamp is found, ';'.
 *
 * The consumer allows comments in and around the consumer... although the
 * Received header specification only allows them before a part, for example,
 * technically speaking this is valid:
 *
 * "FROM machine (host) (comment) BY machine"
 *
 * However, this is not:
 *
 * "FROM machine (host) BY machine WITH (comment) ESMTP"
 *
 * The consumer will allow both.
 *
 * @author Zaahid Bateson
 */
class GenericReceivedConsumerService extends AbstractGenericConsumerService
{
    /**
     * @var string the current part name being parsed.
     *
     * This is always the lower-case name provided to the constructor, not the
     * actual string that started the consumer, which could be in any case.
     */
    protected $partName;

    /**
     * Constructor overridden to include $partName parameter.
     *
     */
    public function __construct(
        HeaderPartFactory $partFactory,
        CommentConsumerService $commentConsumerService,
        string $partName
    ) {
        parent::__construct($partFactory, [ $commentConsumerService ]);
        $this->partName = $partName;
    }

    /**
     * Returns true if the passed token matches (case-insensitively)
     * $this->getPartName() with optional whitespace surrounding it.
     */
    protected function isStartToken(string $token) : bool
    {
        $pattern = '/^' . \preg_quote($this->partName, '/') . '$/i';
        return (\preg_match($pattern, $token) === 1);
    }

    /**
     * Returns true if the token matches (case-insensitively) any of the
     * following, with optional surrounding whitespace:
     *
     * o by
     * o via
     * o with
     * o id
     * o for
     * o ;
     */
    protected function isEndToken(string $token) : bool
    {
        return (\preg_match('/^(by|via|with|id|for|;)$/i', $token) === 1);
    }

    /**
     * Returns a whitespace separator (for filtering ignorable whitespace
     * between parts), and a separator matching the current part name as
     * set on $this->partName.
     *
     * @return string[] an array of regex pattern matchers
     */
    protected function getTokenSeparators() : array
    {
        return [
            '\s+',
            '(\A\s*|\s+)(?i)' . \preg_quote($this->partName, '/') . '(?-i)(?=\s+)'
        ];
    }

    /**
     * Overridden to combine all part values into a single string and return it
     * as the first element, followed by any comment elements as subsequent
     * elements.
     *
     * @param \ZBateson\MailMimeParser\Header\IHeaderPart[] $parts
     * @return \ZBateson\MailMimeParser\Header\IHeaderPart[]
     */
    protected function processParts(array $parts) : array
    {
        $strValue = '';
        $ret = [];
        $filtered = $this->filterIgnoredSpaces($parts);
        foreach ($filtered as $part) {
            if ($part instanceof CommentPart) {
                $ret[] = $part;
                continue;    // getValue() is empty anyway, but for clarity...
            }
            $strValue .= $part->getValue();
        }
        \array_unshift($ret, $this->partFactory->newReceivedPart($this->partName, $strValue));
        return $ret;
    }
}
