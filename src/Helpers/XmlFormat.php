<?php

namespace App\Helpers;

class XmlFormat
{
    /**
     * @param string|\SimpleXMLElement $xml
     * @param bool $formatOutput
     * @param bool $declaration
     * @param int $indentSpaces 2 o 4 (Cantidad de espacios por nivel)
     * @return string
     */
    public static function format($xml, $formatOutput = true, $declaration = true, $indentSpaces = 4)
    {
        // 1. Instanciar directamente forzando UTF-8
        $domDocument = new \DOMDocument('1.0', 'utf-8');
        $domDocument->preserveWhiteSpace = false;
        $domDocument->formatOutput = (bool)$formatOutput;

        // 2. Cargar directamente el string sin pasar por SimpleXML (Ahorro de CPU)
        $xmlString = ($xml instanceof \SimpleXMLElement) ? $xml->asXML() : $xml;
        
        // Evitar errores de parseo por caracteres extraños
        @$domDocument->loadXML($xmlString, LIBXML_NOBLANKS);

        // 3. Generar la salida
        $result = (bool)$declaration ? $domDocument->saveXML() : $domDocument->saveXML($domDocument->documentElement);

        // 4. (Opcional) Cambiar la indentación de 2 a 4 espacios para que se vea mejor
        if ($formatOutput && $indentSpaces === 4) {
            $result = preg_replace('/^(  +)/m', '$1$1', $result);
        }

        return $result;
    }
}
