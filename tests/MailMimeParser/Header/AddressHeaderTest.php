<?php

namespace ZBateson\MailMimeParser\Header;

use PHPUnit\Framework\TestCase;
use ZBateson\MailMimeParser\Header\Consumer\AddressBaseConsumerService;
use ZBateson\MailMimeParser\Header\Consumer\AddressConsumerService;
use ZBateson\MailMimeParser\Header\Consumer\AddressEmailConsumerService;
use ZBateson\MailMimeParser\Header\Consumer\AddressGroupConsumerService;
use ZBateson\MailMimeParser\Header\Consumer\CommentConsumerService;
use ZBateson\MailMimeParser\Header\Consumer\QuotedStringConsumerService;

/**
 * Description of AddressHeaderTest
 *
 * @group Headers
 * @group AddressHeader
 * @covers ZBateson\MailMimeParser\Header\AddressHeader
 * @covers ZBateson\MailMimeParser\Header\AbstractHeader
 * @author Zaahid Bateson
 */
class AddressHeaderTest extends TestCase
{
    // @phpstan-ignore-next-line
    protected $consumerService;

    // @phpstan-ignore-next-line
    protected $mpf;

    private $logger;

    protected function setUp() : void
    {
        $this->logger = \mmpGetTestLogger();
        $charsetConverter = $this->getMockBuilder(\ZBateson\MbWrapper\MbWrapper::class)
            ->setMethods()
            ->getMock();
        $pf = $this->getMockBuilder(\ZBateson\MailMimeParser\Header\Part\HeaderPartFactory::class)
            ->setConstructorArgs([$this->logger, $charsetConverter])
            ->setMethods()
            ->getMock();
        $mpf = $this->getMockBuilder(\ZBateson\MailMimeParser\Header\Part\MimeTokenPartFactory::class)
            ->setConstructorArgs([$this->logger, $charsetConverter])
            ->setMethods()
            ->getMock();
        $qscs = $this->getMockBuilder(QuotedStringConsumerService::class)
            ->setConstructorArgs([$this->logger, $pf])
            ->setMethods()
            ->getMock();
        $ccs = $this->getMockBuilder(CommentConsumerService::class)
            ->setConstructorArgs([$this->logger, $mpf, $qscs])
            ->setMethods()
            ->getMock();
        $agcs = $this->getMockBuilder(AddressGroupConsumerService::class)
            ->setConstructorArgs([$this->logger, $pf])
            ->setMethods()
            ->getMock();
        $aecs = $this->getMockBuilder(AddressEmailConsumerService::class)
            ->setConstructorArgs([$this->logger, $pf, $ccs, $qscs])
            ->setMethods()
            ->getMock();
        $acs = $this->getMockBuilder(AddressConsumerService::class)
            ->setConstructorArgs([$this->logger, $mpf, $agcs, $aecs, $ccs, $qscs])
            ->setMethods()
            ->getMock();
        $this->consumerService = $this->getMockBuilder(AddressBaseConsumerService::class)
            ->setConstructorArgs([$this->logger, $pf, $acs])
            ->setMethods()
            ->getMock();
        $this->mpf = $mpf;
    }

    private function newAddressHeader($name, $value)
    {
        return new AddressHeader($name, $value, $this->logger, $this->mpf, $this->consumerService);
    }

    public function testEmptyHeader() : void
    {
        $header = $this->newAddressHeader('TO', '');
        $this->assertEquals('', $header->getValue());
        $this->assertNull($header->getPersonName());
    }

    public function testSingleAddress() : void
    {
        $header = $this->newAddressHeader('From', 'koolaid@dontdrinkit.com');
        $this->assertEquals('koolaid@dontdrinkit.com', $header->getValue());
        $this->assertEmpty($header->getPersonName());
        $this->assertEquals('From', $header->getName());
    }

    public function testAddressHeaderToString() : void
    {
        $header = $this->newAddressHeader('From', 'koolaid@dontdrinkit.com');
        $this->assertEquals('From: koolaid@dontdrinkit.com', $header);
    }

    public function testSingleAddressWithName() : void
    {
        $header = $this->newAddressHeader('From', 'Kool Aid <koolaid@dontdrinkit.com>');
        $this->assertEquals('koolaid@dontdrinkit.com', $header->getValue());
        $this->assertEquals('Kool Aid', $header->getPersonName());
        $addresses = $header->getParts();
        $this->assertCount(1, $addresses);
        $this->assertEquals('Kool Aid', $addresses[0]->getName());
        $this->assertEquals('koolaid@dontdrinkit.com', $addresses[0]->getValue());
    }

    public function testSingleAddressWithQuotedName() : void
    {
        $header = $this->newAddressHeader('To', '"Jürgen Schmürgen" <schmuergen@example.com>');
        $addresses = $header->getParts();
        $this->assertCount(1, $addresses);
        $this->assertEquals('Jürgen Schmürgen', $addresses[0]->getName());
        $this->assertEquals('schmuergen@example.com', $addresses[0]->getEmail());
        $this->assertEquals('Jürgen Schmürgen', $header->getPersonName());
        $this->assertEquals('schmuergen@example.com', $header->getEmail());
    }

    public function testComplexSingleAddress() : void
    {
        // the domain is invalid here
        $header = $this->newAddressHeader(
            'From',
            '=?US-ASCII?Q?Kilgore?= "Trout" <kilgore (writer) trout@"ilium" .ny. us>'
        );
        $addresses = $header->getParts();
        $this->assertCount(1, $addresses);
        $this->assertEquals('Kilgore Trout', $addresses[0]->getName());
        $this->assertEquals('kilgoretrout@"ilium".ny.us', $addresses[0]->getEmail());
    }

    public function testMultipleEncodingSingleAddress() : void
    {
        $header = $this->newAddressHeader(
            'To',
            '=?ISO-8859-1?Q?f=F3=F3?=  =?UTF-8?Q?b=C3=A1r?= <test@example.com>'
        );
        $addresses = $header->getParts();
        $this->assertCount(1, $addresses);
        $this->assertEquals('fóó  bár', $addresses[0]->getName());
        $this->assertEquals('test@example.com', $addresses[0]->getEmail());
    }

    public function testSingleAddressWithEscapedToken() : void
    {
        $header = $this->newAddressHeader('From', '\"Kool Aid\" <koolaid@dontdrinkit.com>');
        $this->assertEquals('koolaid@dontdrinkit.com', $header->getValue());
        $this->assertEquals('"Kool Aid"', $header->getPersonName());
        $addresses = $header->getParts();
        $this->assertCount(1, $addresses);
        $this->assertEquals('"Kool Aid"', $addresses[0]->getName());
        $this->assertEquals('koolaid@dontdrinkit.com', $addresses[0]->getValue());
    }

    public function testMultipleAddresses() : void
    {
        $header = $this->newAddressHeader(
            'To',
            'thepilot@earth.com, The Little   Prince <theprince@ihatebaobabs.com> , '
            . '"The Fox"    <thefox@ilovetheprince.com>   ,    therose@pureawesome.com'
        );
        $addresses = $header->getParts();
        $this->assertCount(4, $addresses);
        $this->assertEquals('thepilot@earth.com', $addresses[0]->getEmail());
        $this->assertEquals('theprince@ihatebaobabs.com', $addresses[1]->getEmail());
        $this->assertEquals('The Little Prince', $addresses[1]->getName());
        $this->assertEquals('thefox@ilovetheprince.com', $addresses[2]->getEmail());
        $this->assertEquals('The Fox', $addresses[2]->getName());
        $this->assertEquals('therose@pureawesome.com', $addresses[3]->getEmail());
    }

    public function testAddressGroupParts() : void
    {
        $header = $this->newAddressHeader(
            'Cc',
            '=?US-ASCII?Q?House?= Stark: Arya Stark <arya(strong:personality)@winterfell.com>, robb@winterfell.com,'
            . 'Jon Snow <jsnow(that\'s right;)@nightswatch.com>; "House Lannister": tywin@lannister.com,'
            . '"Jaime Lannister" <jaime@lannister.com>, tyrion@lannister.com, Cersei Lannister <"cersei & cersei"@lannister.com>'
        );
        $parts = $header->getParts();
        $this->assertCount(2, $parts);

        $starks = $parts[0];
        $lannisters = $parts[1];
        $this->assertEquals('House Stark', $starks->getName());
        $this->assertEquals('House Lannister', $lannisters->getName());

        $this->assertCount(3, $starks->getAddresses());
        $this->assertCount(4, $lannisters->getAddresses());
    }

    public function testHasAddress() : void
    {
        $header = $this->newAddressHeader(
            'Cc',
            '=?US-ASCII?Q?House?= Stark: Arya Stark <arya(strong:personality)@winterfell.com>, robb@winterfell.com,'
            . 'Jon Snow <jsnow(that\'s right;)@nightswatch.com>; "House Lannister": tywin@lannister.com,'
            . '"Jaime Lannister" <jaime@lannister.com>, tyrion@lannister.com, Cersei Lannister <"cersei & cersei"@lannister.com>;'
            . 'maxpayne@addressunknown.com'
        );
        $this->assertTrue($header->hasAddress('arya@winterfell.com'));
        $this->assertTrue($header->hasAddress('jsnow@nightswatch.com'));
        $this->assertTrue($header->hasAddress('"cersei & cersei"@lannister.com'));
        $this->assertTrue($header->hasAddress('maxpayne@addressunknown.com'));
        $this->assertFalse($header->hasAddress('nonexistent@example.com'));
    }

    public function testGetAddresses() : void
    {
        $header = $this->newAddressHeader(
            'Cc',
            '=?US-ASCII?Q?House?= Stark: Arya Stark <arya(strong:personality)@winterfell.com>, robb@winterfell.com,'
            . 'Jon Snow <jsnow(that\'s right;)@nightswatch.com>; "House Lannister": tywin@lannister.com,'
            . '"Jaime Lannister" <jaime@lannister.com>, tyrion@lannister.com, Cersei Lannister <"cersei & cersei"@lannister.com>;'
            . 'maxpayne@addressunknown.com'
        );
        $addresses = $header->getAddresses();
        $this->assertCount(8, $addresses);
        $parts = $header->getParts();

        foreach ($parts[0]->getAddresses() as $addr) {
            $this->assertSame($addr, \current($addresses));
            \next($addresses);
        }
        foreach ($parts[1]->getAddresses() as $addr) {
            $this->assertSame($addr, \current($addresses));
            \next($addresses);
        }
        $this->assertEquals('maxpayne@addressunknown.com', \current($addresses)->getEmail());
    }

    public function testGetGroups() : void
    {
        $header = $this->newAddressHeader(
            'Cc',
            '=?US-ASCII?Q?House?= Stark: Arya Stark <arya(strong:personality)@winterfell.com>, robb@winterfell.com,'
            . 'Jon Snow <jsnow(that\'s right;)@nightswatch.com>; "House Lannister": tywin@lannister.com,'
            . '"Jaime Lannister" <jaime@lannister.com>, tyrion@lannister.com, Cersei Lannister <"cersei & cersei"@lannister.com>;'
            . 'maxpayne@addressunknown.com'
        );
        $groups = $header->getGroups();
        $parts = $header->getParts();
        $this->assertCount(2, $groups);
        $this->assertSame($parts[0], $groups[0]);
        $this->assertSame($parts[1], $groups[1]);
    }

    public function testEmptyAddressGroup() : void
    {
        $header = $this->newAddressHeader(
            'Cc',
            'House Stark:;'
        );
        $parts = $header->getParts();
        $this->assertCount(1, $parts);

        $starks = $parts[0];
        $this->assertEquals('House Stark', $starks->getName());
        $this->assertCount(0, $starks->getAddresses());
    }
}
