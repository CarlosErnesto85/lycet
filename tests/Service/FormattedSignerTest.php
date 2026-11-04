<?php

namespace App\Tests\Service;

use App\Service\ConfigProviderInterface;
use App\Service\Greenter\FormattedSigner;
use App\Service\SeeFactory;
use Greenter\See;
use Greenter\XMLSecLibs\Sunat\SignedXml;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Guards the custom XML formatting/signing so a Greenter update cannot break it silently.
 */
class FormattedSignerTest extends WebTestCase
{
    private const EXPECTED_SIGN_ID = 'Sign20480072872-PESQUERADIAMANTESA';

    public function testInvoiceXmlIsFormattedAndSignedWithCustomId()
    {
        $xml = $this->requestInvoiceXml();

        $doc = new \DOMDocument();
        $this->assertTrue($doc->loadXML($xml), 'Response must be valid XML');
        $this->assertSame('Invoice', $doc->documentElement->nodeName);

        // 4-space indentation from XmlFormat
        $this->assertMatchesRegularExpression('/^    <cbc:UBLVersionID>2\.1<\/cbc:UBLVersionID>$/m', $xml);
        $this->assertMatchesRegularExpression('/^        <ext:UBLExtension>$/m', $xml);

        // Signature Id and reference URI use Sign{RUC}-{RazonSocial}
        $this->assertStringContainsString('Id="' . self::EXPECTED_SIGN_ID . '"', $xml);
        $this->assertStringContainsString('<cbc:URI>#' . self::EXPECTED_SIGN_ID . '</cbc:URI>', $xml);
        $this->assertStringNotContainsString('GREENTER-SIGN', $xml);
        $this->assertStringNotContainsString('Id="GreenterSign"', $xml);
        $this->assertStringContainsString('<ds:SignatureValue>', $xml);
    }

    public function testInvoiceSignatureIsStillValidAfterFormatting()
    {
        $xml = $this->requestInvoiceXml();

        $this->assertTrue((new SignedXml())->verifyXml($xml), 'Digital signature must verify');
    }

    public function testExoneratedTaxSubtotalUsesTwoDecimalsAndSignatureIsValid()
    {
        $xml = $this->requestInvoiceXml('invoice-exonerada.json');

        $doc = new \DOMDocument();
        $this->assertTrue($doc->loadXML($xml), 'Response must be valid XML');

        $xpath = new \DOMXPath($doc);
        $xpath->registerNamespace('cac', 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2');
        $xpath->registerNamespace('cbc', 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2');

        $this->assertSame(
            '0.00',
            $xpath->evaluate('string(/*/cac:TaxTotal/cac:TaxSubtotal[cac:TaxCategory/cac:TaxScheme/cbc:ID="9997"]/cbc:TaxAmount)')
        );
        $this->assertDoesNotMatchRegularExpression('/>\s*0\s*<\/cbc:TaxAmount>/', $xml);
        $this->assertTrue((new SignedXml())->verifyXml($xml), 'Digital signature must verify');
    }

    public function testSeeFactoryDoesNotWrapSignerTwice()
    {
        $see = new See();
        $config = $this->createMock(ConfigProviderInterface::class);
        $reader = $this->createMock(\App\Service\FileDataReader::class);

        new SeeFactory($config, $config, $reader, $see);
        $first = $see->getFactory()->getSigner();

        new SeeFactory($config, $config, $reader, $see);
        $second = $see->getFactory()->getSigner();

        $this->assertInstanceOf(FormattedSigner::class, $first);
        $this->assertSame($first, $second, 'Signer must not be re-wrapped');

        $inner = (new \ReflectionProperty(FormattedSigner::class, 'innerSigner'));
        $inner->setAccessible(true);
        $this->assertNotInstanceOf(FormattedSigner::class, $inner->getValue($first));
    }

    private function requestInvoiceXml(string $document = 'invoice.json'): string
    {
        $client = static::createClient();
        $client->getContainer()->set(ConfigProviderInterface::class, $this->getFileConfig());

        $client->request(
            'POST',
            '/api/v1/invoice/xml?token=123456',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            file_get_contents(__DIR__ . '/../Resources/documents/' . $document)
        );

        $response = $client->getResponse();
        $this->assertSame(200, $response->getStatusCode());

        return $client->getInternalResponse()->getContent();
    }

    private function getFileConfig(): ConfigProviderInterface
    {
        $stub = $this->createMock(ConfigProviderInterface::class);
        $stub->method('get')->willReturnCallback(function ($key) {
            return $key === 'certificate'
                ? file_get_contents(__DIR__ . '/../Resources/cert.pem')
                : '';
        });

        return $stub;
    }
}
