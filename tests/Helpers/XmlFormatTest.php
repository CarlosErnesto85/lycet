<?php

namespace App\Tests\Helpers;

use App\Helpers\XmlFormat;
use PHPUnit\Framework\TestCase;

class XmlFormatTest extends TestCase
{
    public function testFormatIndentsWithFourSpaces()
    {
        $result = XmlFormat::format('<?xml version="1.0" encoding="utf-8"?><root><child><leaf>1</leaf></child></root>');

        $expected = "<?xml version=\"1.0\" encoding=\"utf-8\"?>\n"
            . "<root>\n"
            . "    <child>\n"
            . "        <leaf>1</leaf>\n"
            . "    </child>\n"
            . "</root>\n";

        $this->assertSame($expected, $result);
    }

    public function testFormatWithTwoSpacesAndWithoutDeclaration()
    {
        $result = XmlFormat::format('<root><child>1</child></root>', true, false, 2);

        $this->assertSame("<root>\n  <child>1</child>\n</root>", $result);
    }

    public function testFormatAcceptsSimpleXmlElement()
    {
        $result = XmlFormat::format(new \SimpleXMLElement('<root><child/></root>'));

        $this->assertStringContainsString("\n    <child/>", $result);
    }

    public function testFormatThrowsOnInvalidXml()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('No se pudo formatear el XML');

        XmlFormat::format('<root><child></root>');
    }

    public function testFormatThrowsOnEmptyContent()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('contenido vacío');

        XmlFormat::format('');
    }

    public function testFormatDoesNotLeakLibxmlErrors()
    {
        $previous = libxml_use_internal_errors(false);
        try {
            XmlFormat::format('<broken>');
        } catch (\InvalidArgumentException $e) {
        }

        $this->assertFalse(libxml_use_internal_errors($previous));
        $this->assertEmpty(libxml_get_errors());
    }
}
