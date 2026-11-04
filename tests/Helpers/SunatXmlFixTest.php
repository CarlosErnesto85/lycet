<?php

namespace App\Tests\Helpers;

use App\Helpers\SunatXmlFix;
use PHPUnit\Framework\TestCase;

class SunatXmlFixTest extends TestCase
{
    /**
     * @dataProvider currencyProvider
     */
    public function testZeroIsRewrittenAsTwoDecimals(string $currency)
    {
        $xml = '<cbc:TaxAmount currencyID="' . $currency . '">0</cbc:TaxAmount>';

        $this->assertSame(
            '<cbc:TaxAmount currencyID="' . $currency . '">0.00</cbc:TaxAmount>',
            SunatXmlFix::normalizeZeroTaxAmount($xml)
        );
    }

    public function currencyProvider(): array
    {
        return [['PEN'], ['USD']];
    }

    /**
     * @dataProvider unchangedProvider
     */
    public function testOtherAmountsAreNotChanged(string $value)
    {
        $xml = '<cbc:TaxAmount currencyID="PEN">' . $value . '</cbc:TaxAmount>';

        $this->assertSame($xml, SunatXmlFix::normalizeZeroTaxAmount($xml));
    }

    public function unchangedProvider(): array
    {
        return [['0.00'], ['18.00'], ['10'], ['0.5'], ['100']];
    }

    public function testOtherElementsAreNotChanged()
    {
        $xml = '<cbc:Percent>0</cbc:Percent><cbc:TaxableAmount currencyID="PEN">0</cbc:TaxableAmount>';

        $this->assertSame($xml, SunatXmlFix::normalizeZeroTaxAmount($xml));
    }

    public function testAllOccurrencesAreRewritten()
    {
        $xml = '<cac:TaxTotal>'
            . '<cbc:TaxAmount currencyID="PEN">0</cbc:TaxAmount>'
            . '<cbc:TaxAmount currencyID="PEN">18.00</cbc:TaxAmount>'
            . '<cbc:TaxAmount currencyID="PEN">0</cbc:TaxAmount>'
            . '</cac:TaxTotal>';

        $expected = '<cac:TaxTotal>'
            . '<cbc:TaxAmount currencyID="PEN">0.00</cbc:TaxAmount>'
            . '<cbc:TaxAmount currencyID="PEN">18.00</cbc:TaxAmount>'
            . '<cbc:TaxAmount currencyID="PEN">0.00</cbc:TaxAmount>'
            . '</cac:TaxTotal>';

        $this->assertSame($expected, SunatXmlFix::normalizeZeroTaxAmount($xml));
    }
}
