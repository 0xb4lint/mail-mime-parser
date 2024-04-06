<?php

namespace ZBateson\MailMimeParser\Header\Part;

use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel;
use ZBateson\MbWrapper\MbWrapper;

/**
 * Description of DateTest
 *
 * @group HeaderParts
 * @group DatePart
 * @covers ZBateson\MailMimeParser\Header\Part\DatePart
 * @covers ZBateson\MailMimeParser\Header\Part\HeaderPart
 * @author Zaahid Bateson
 */
class DatePartTest extends TestCase
{
    // @phpstan-ignore-next-line
    private $mb;
    private $hpf;

    protected function setUp() : void
    {
        $this->mb = new MbWrapper();
        $this->hpf = $this->getMockBuilder(HeaderPartFactory::class)
            ->setConstructorArgs([$this->mb])
            ->setMethods()
            ->getMock();
    }

    private function getTokenMock(string $name) : Token
    {
        return $this->getMockBuilder(Token::class)
            ->setConstructorArgs([$this->mb, $name])
            ->setMethods()
            ->getMock();
    }

    public function testDateString() : void
    {
        $values = [
            ['2000-05-17T19:08:29-0400', 'Wed, 17 May 2000 19:08:29 -0400'],
            ['2014-03-13T15:02:47+0000', 'Thu, 13 Mar 14 15:02:47 +0000'],
            ['1999-05-06T15:02:47+0000', 'Thu, 6 May 99 15:02:47 +0000'],
            ['1999-05-06T15:02:47+0000', 'Thu, 6 May 1999 15:02:47 UT'],
            ['2014-03-13T15:02:47+0000', 'Thu, 13 Mar 2014 15:02:47 0000'] // Not RFC-compliant
        ];

        foreach ($values as $value) {
            [$expected, $raw] = $value;
            $part = new DatePart($this->mb, $this->hpf, [$this->getTokenMock($raw)]);
            $this->assertEquals($raw, $part->getValue(), 'Testing ' . $raw);
            $this->assertNotEmpty($part->getDateTime(), 'Testing ' . $raw);
            $this->assertEquals($expected, $part->getDateTime()->format(\DateTime::ISO8601), 'Testing ' . $raw);
        }
    }

    public function testInvalidDate() : void
    {
        $value = 'Invalid Date';
        $part = new DatePart($this->mb, $this->hpf, [$this->getTokenMock($value)]);
        $this->assertEquals($value, $part->getValue());
        $date = $part->getDateTime();
        $this->assertNull($date);

        $errs = $part->getErrors(false, LogLevel::ERROR);
        $this->assertCount(1, $errs);
        $this->assertEquals("Unable to parse date from header: \"{$value}\"", $errs[0]->getMessage());
        $this->assertEquals(LogLevel::ERROR, $errs[0]->getPsrLevel());
    }
}
