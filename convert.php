<?php

use Greenter\XMLSecLibs\Certificate\X509Certificate;
use Greenter\XMLSecLibs\Certificate\X509ContentType;

require 'vendor/autoload.php';

$pfx = file_get_contents('./data/20454651015/LLAMA-PE-CERTIFICADO-DEMO-20454651015.pfx');
$password = '123456';

$certificate = new X509Certificate($pfx, $password);
$pem = $certificate->export(X509ContentType::PEM);

file_put_contents('./data/20454651015/20454651015-cert.pem', $pem);

?>