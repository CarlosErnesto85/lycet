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
     * @throws \InvalidArgumentException Si el XML no se puede parsear.
     */
    public static function format($xml, $formatOutput = true, $declaration = true, $indentSpaces = 4)
    {
        // 1. Instanciar directamente forzando UTF-8
        $domDocument = new \DOMDocument('1.0', 'utf-8');
        $domDocument->preserveWhiteSpace = false;
        $domDocument->formatOutput = (bool)$formatOutput;

        // 2. Cargar directamente el string sin pasar por SimpleXML (Ahorro de CPU)
        $xmlString = ($xml instanceof \SimpleXMLElement) ? $xml->asXML() : $xml;

        // 3. Parsear capturando errores de libxml. Si falla, no se debe continuar:
        //    de lo contrario se firmaría un documento vacío.
        $previous = libxml_use_internal_errors(true);
        libxml_clear_errors();
        $loaded = is_string($xmlString) && $xmlString !== ''
            && $domDocument->loadXML($xmlString, LIBXML_NOBLANKS);
        $errors = libxml_get_errors();
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (!$loaded) {
            $detail = $errors ? trim($errors[0]->message) . ' (línea ' . $errors[0]->line . ')' : 'contenido vacío';
            throw new \InvalidArgumentException('No se pudo formatear el XML: ' . $detail);
        }

        // 4. Generar la salida
        $result = (bool)$declaration ? $domDocument->saveXML() : $domDocument->saveXML($domDocument->documentElement);

        // 5. (Opcional) Cambiar la indentación de 2 a 4 espacios para que se vea mejor
        if ($formatOutput && $indentSpaces === 4) {
            $result = preg_replace('/^(  +)/m', '$1$1', $result);
        }

        return $result;
    }
}
