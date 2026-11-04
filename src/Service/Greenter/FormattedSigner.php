<?php

namespace App\Service\Greenter;

use Greenter\XMLSecLibs\Sunat\SignedXml;
use App\Helpers\SunatXmlFix;
use App\Helpers\XmlFormat;

class FormattedSigner extends SignedXml
{
    /**
     * @var SignedXml
     */
    private $innerSigner;

    public function __construct(SignedXml $innerSigner)
    {
        $this->innerSigner = $innerSigner;
    }

    /**
     * @param string $cert
     */
    public function setCertificate($cert)
    {
        parent::setCertificate($cert);
        $this->innerSigner->setCertificate($cert);
    }

    /**
     * @param string $content
     * @return string
     */
    public function signXml($content)
    {
        // 1. Extraer RUC y Razón Social del XML sin firmar
        $ruc = '';
        $razonSocial = '';
        if (preg_match('/<cac:SignatoryParty>[\s\S]*?<cbc:ID[^>]*>([^<]+)<\/cbc:ID>/', $content, $m)) {
            $ruc = trim($m[1]);
        }
        if (preg_match('/<cac:SignatoryParty>[\s\S]*?<cbc:Name>(?:<!\[CDATA\[)?(.*?)(?:\]\]>)?<\/cbc:Name>/', $content, $m)) {
            $razonSocial = trim($m[1]);
        }

        $signId = 'GreenterSign';
        if (!empty($ruc) && !empty($razonSocial)) {
            // El atributo ID en XML NO puede empezar con un número y solo debe tener caracteres válidos.
            $cleanRazonSocial = preg_replace('/[^A-Za-z0-9_\-]/', '', $razonSocial);
            $signId = 'Sign' . $ruc . '-' . $cleanRazonSocial;
            // 2. Reemplazar URI en el XML sin firmar
            $content = str_replace('#GREENTER-SIGN', '#' . $signId, $content);
        }

        // 3. Corregir TaxAmount "0" -> "0.00" (debe ser antes de firmar) y formatear el XML
        $content = SunatXmlFix::normalizeZeroTaxAmount($content);
        $formattedContent = XmlFormat::format($content);

        // 4. Firmar el documento
        $signedXml = $this->innerSigner->signXml($formattedContent);

        // 5. Reemplazar el Id del bloque de firma insertado por Greenter
        if ($signId !== 'GreenterSign') {
            $signedXml = str_replace('Id="GreenterSign"', 'Id="' . $signId . '"', $signedXml);
        }

        return $signedXml;
    }
}
