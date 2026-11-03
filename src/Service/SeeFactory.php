<?php

namespace App\Service;

use App\Service\Greenter\FormattedSigner;
use Greenter\Model\Despatch\Despatch;
use Greenter\Model\Perception\Perception;
use Greenter\Model\Retention\Retention;
use Greenter\Model\Voided\Reversion;
use Greenter\See;
use Greenter\XMLSecLibs\Sunat\SignedXml;

class SeeFactory
{
    /**
     * @var ConfigProviderInterface
     */
    private $config;

    /**
     * @var ConfigProviderInterface
     */
    private $fileProvider;

    /**
     * @var FileDataReader
     */
    private $fileReader;

    /**
     * @var See
     */
    private $see;

    /**
     * SeeFactory constructor.
     * @param ConfigProviderInterface $config
     * @param ConfigProviderInterface $fileProvider
     * @param FileDataReader $fileReader
     * @param See $see
     */
    public function __construct(ConfigProviderInterface $config, ConfigProviderInterface $fileProvider, FileDataReader $fileReader, See $see)
    {
        $this->config = $config;
        $this->fileProvider = $fileProvider;
        $this->fileReader = $fileReader;
        $this->see = $see;

        // Wrap the original signer with FormattedSigner to format XML before signing.
        // See is a shared service, so skip wrapping if a previous SeeFactory already did it.
        $factory = $this->see->getFactory();
        $originalSigner = $factory->getSigner();
        if (!$originalSigner instanceof FormattedSigner) {
            $factory->setSigner(new FormattedSigner($originalSigner ?? new SignedXml()));
        }
    }

    public function build(string $class, ?string $ruc): See
    {
        if (!empty($ruc) && $this->configureSeeWithRuc($ruc, $class)) {
            return $this->see;
        }

        $this->configureSeeWithEnv($class);
        return $this->see;
    }

    private function configureSeeWithRuc(string $ruc, string $class): bool
    {
        $jsonCompanies = $this->fileProvider->get('companies');
        if (empty($jsonCompanies)) {
            return false;
        }

        $companies = json_decode($jsonCompanies, true);

        if (!array_key_exists($ruc, $companies)) {
            return false;
        }

        $config = $companies[$ruc];
        $this->see->setCredentials($config['SOL_USER'], $config['SOL_PASS']);
        $this->see->setCertificate($this->fileReader->getContents($config['certificate']));
        $this->see->setService($this->getUrlService($class, $config));

        return true;
    }

    private function configureSeeWithEnv(string $class)
    {
        $this->see->setCredentials($this->config->get('SOL_USER'), $this->config->get('SOL_PASS'));
        $this->see->setCertificate($this->fileProvider->get('certificate'));
        $this->see->setService($this->getUrlService($class));
    }

    private function getUrlService($className, $config = [])
    {
        $key = 'FE_URL';
        switch ($className) {
            case Perception::class:
            case Retention::class:
            case Reversion::class:
                $key = 'RE_URL';
                break;
            case Despatch::class:
                $key = 'GUIA_URL';
                break;
        }

        if(array_key_exists($key, $config)) {
          return $config[$key];
        }

        return $this->config->get($key);
    }
}
