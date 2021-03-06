<?php

namespace Hautelook\AliceBundle\Alice;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Persistence\ObjectManager;
use Psr\Log\LoggerInterface;

/**
 * Loader
 * @author Baldur Rensch <brensch@gmail.com>
 */
class Loader
{
    /**
     * @var array
     */
    private $providers;

    /**
     * @var array
     */
    private $loaders;

    /**
     * @var ObjectManager
     */
    private $objectManager;

    /**
     * @var Doctrine
     */
    private $persister;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var ArrayCollection
     */
    private $references;

    /**
     * @param                 $loaders
     * @param LoggerInterface $logger
     */
    public function __construct($loaders, LoggerInterface $logger = null)
    {
        $this->loaders = $loaders;
        $this->logger = $logger;
        $this->references = new ArrayCollection();
    }

    /**
     * @param ObjectManager $manager
     */
    public function setObjectManager(ObjectManager $manager)
    {
        $this->objectManager = $manager;

        $this->persister = new Doctrine($this->objectManager);

        $newReferences = array();
        foreach ($this->references as $name => $reference) {
            $newReferences[$name] = $this->persister->merge($reference);
        }
        $this->references = new ArrayCollection($newReferences);

        /** @var $loader \Nelmio\Alice\Loader\Base */
        foreach ($this->loaders as $loader) {
            $loader->setLogger($this->logger);
            $loader->setORM($this->persister);
            $loader->setReferences($newReferences);
        }
    }

    /**
     * @param array<string> $files
     */
    public function load(array $files)
    {
        /** @var $loader \Nelmio\Alice\Loader\Base */
        $loader = $this->getLoader('yaml');
        $loader->setProviders($this->providers);

        $objects = array();
        foreach ($files as $file) {
            $set = $loader->load($file);
            $this->persister->persist($set);

            $objects = array_merge($objects, $set);
        }

        foreach ($loader->getReferences() as $name => $obj) {
            $this->persister->detach($obj);
            $this->references->set($name, $obj);
        }
    }

    /**
     * @param array $providers
     */
    public function setProviders(array $providers)
    {
        $this->providers = $providers;
    }

    /**
     * @param string $key
     *
     * @throws \InvalidArgumentException
     * @return mixed
     */
    protected function getLoader($key)
    {
        if (empty($this->loaders[$key])) {
            throw new \InvalidArgumentException("Unknown loader type: {$key}");
        }
        /*
        if (is_string($file) && preg_match('{\.ya?ml(\.php)?$}', $file)) {
            $loader = self::getLoader('Yaml', $options);
        } elseif ((is_string($file) && preg_match('{\.php$}', $file)) || is_array($file)) {
            $loader = self::getLoader('Base', $options);
        } else {
            throw new \InvalidArgumentException('Unknown file/data type: '.gettype($file).' ('.json_encode($file).')');
        }
        */
        /** @var $loader \Nelmio\Alice\LoaderInterface */
        $loader = $this->loaders[$key];

        return $loader;
    }
}
