<?php
/**
 * This file is part of the ZBateson\MailMimeParser project.
 *
 * @license http://opensource.org/licenses/bsd-license.php BSD
 */

namespace ZBateson\MailMimeParser\Header\Part;

/**
 * A literal header string part.  The value of the part is stripped of CR and LF
 * characters, but otherwise not transformed or changed in any way.
 *
 * @author Zaahid Bateson
 */
class LiteralPart extends HeaderPart
{
    /**
     * Creates a LiteralPart out of the passed string token
     */
    public function __construct(MbWrapperService $charsetConverter, ?string $token = null)
    {
        parent::__construct($charsetConverter);
        $this->value = $token;
        if ($token !== null) {
            $this->value = \preg_replace('/\r|\n/', '', $this->convertEncoding($token));
        }
    }
}
