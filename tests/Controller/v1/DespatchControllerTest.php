<?php

namespace App\Tests\Controller\v1;

use App\Service\ConfigProviderInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class DespatchControllerTest extends WebTestCase
{
    /**
     * fecEntregaBienes is only rendered since greenter/xml v5.3.0.
     */
    public function testXmlIncludesFechaEntregaBienes()
    {
        $client = static::createClient();
        $client->getContainer()->set(ConfigProviderInterface::class, $this->getFileConfig());

        $client->request(
            'POST',
            '/api/v1/despatch/xml?token=123456',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            file_get_contents(__DIR__.'/../../Resources/documents/despatch.json')
        );

        $this->assertSame(200, $client->getResponse()->getStatusCode());

        $doc = new \DOMDocument();
        $this->assertTrue($doc->loadXML($client->getInternalResponse()->getContent()));

        $xpath = new \DOMXPath($doc);
        $xpath->registerNamespace('cac', 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2');
        $xpath->registerNamespace('cbc', 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2');

        $this->assertSame(
            '2026-11-05',
            $xpath->evaluate('string(//cac:Shipment//cac:LoadingTransportEvent/cbc:OccurrenceDate)')
        );
    }

    private function getFileConfig(): ConfigProviderInterface
    {
        $stub = $this->createMock(ConfigProviderInterface::class);
        $stub->method('get')->willReturnCallback(function ($key) {
            return $key === 'certificate'
                ? file_get_contents(__DIR__.'/../../Resources/cert.pem')
                : '';
        });

        return $stub;
    }
}
