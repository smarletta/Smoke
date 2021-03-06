<?php

namespace whm\Smoke\Config;

use Cache\Adapter\Filesystem\FilesystemCachePool;
use League\Flysystem\Adapter\Local;
use League\Flysystem\Filesystem;
use phm\HttpWebdriverClient\Http\Client\Decorator\CacheDecorator;
use phm\HttpWebdriverClient\Http\Client\Guzzle\GuzzleClient;
use phm\HttpWebdriverClient\Http\Client\HttpClient;
use phmLabs\Components\Annovent\Dispatcher;
use PhmLabs\Components\Init\Init;
use Symfony\Component\Yaml\Yaml;
use whm\Html\Uri;
use whm\Smoke\Http\Session;
use whm\Smoke\Rules\Rule;
use whm\Smoke\Scanner\SessionContainer;

class Configuration
{
    const DEFAULT_SETTINGS = 'analyze.yml';

    private $startUri;

    private $rules = [];

    private $configArray;

    private $eventDispatcher;

    private $extensions = array();

    private $runLevels = array();

    /**
     * @var SessionContainer
     */
    private $sessionContainer;

    public function __construct(Uri $uri, Dispatcher $eventDispatcher, array $configArray, array $defaultSettings = null)
    {
        $this->eventDispatcher = $eventDispatcher;
        Init::registerGlobalParameter('_configuration', $this);

        $this->initConfigArray($configArray, $defaultSettings);

        if (array_key_exists('sessions', $this->configArray)) {
            $this->initSessionContainer($this->configArray['sessions']);
        } else {
            $this->sessionContainer = new SessionContainer();
        }

        if (array_key_exists('extensions', $this->configArray)) {
            $this->addListener($this->configArray['extensions']);
        }

        if (!array_key_exists('rules', $this->configArray)) {
            $this->configArray['rules'] = [];
        }

        $this->startUri = $uri;
        $this->initRules($this->configArray['rules']);
    }

    private function initConfigArray(array $configArray, array $defaultSettings = null)
    {
        if ($defaultSettings === null) {
            $defaultSettings = Yaml::parse(file_get_contents(__DIR__ . '/../settings/' . self::DEFAULT_SETTINGS));
        }

        if (count($configArray) === 0) {
            $configArray = $defaultSettings;
        }

        if (array_key_exists('options', $configArray)) {
            if (array_key_exists('extendDefault', $configArray['options'])) {
                if ($configArray['options']['extendDefault'] === true) {
                    $configArray = array_replace_recursive($defaultSettings, $configArray);
                }
            }
        }

        $this->configArray = $configArray;
    }

    private function initSessionContainer(array $sessionsArray)
    {
        $this->sessionContainer = new SessionContainer();

        foreach ($sessionsArray as $sessionName => $sessionsElement) {
            $session = new Session();

            if (array_key_exists('cookies', $sessionsElement)) {
                foreach ($sessionsElement['cookies'] as $key => $value) {
                    $session->addCookie($key, $value);
                }
            }

            $this->sessionContainer->addSession($sessionName, $session);
        }
    }

    private function addListener(array $listenerArray)
    {
        foreach ($listenerArray as $key => $listenerConfig) {
            $extension = Init::initialize($listenerConfig);
            $this->extensions[$key] = $extension;
            $this->eventDispatcher->connectListener($extension);
        }
    }

    /**
     * @return Uri
     */
    public function getStartUri()
    {
        return $this->startUri;
    }

    /**
     * This function initializes all the rules and sets the log level.
     *
     * @param array $rulesArray
     */
    private function initRules(array $rulesArray)
    {
        $this->rules = Init::initializeAll($rulesArray);
    }

    /**
     * Returns the log level of a given rule.
     *
     * @param string $key
     *
     * @return int
     */
    public function getRuleRunLevel($key)
    {
        return $this->runLevels[$key];
    }

    /**
     * @return Rule[]
     */
    public function getRules()
    {
        return $this->rules;
    }

    public function getSessionContainer()
    {
        return $this->sessionContainer;
    }

    public function hasSection($section)
    {
        return array_key_exists($section, $this->configArray);
    }

    /**
     * @param $section
     *
     * @return array
     */
    public function getSection($section)
    {
        if ($this->hasSection($section)) {
            return $this->configArray[$section];
        } else {
            throw new \RuntimeException('The section (' . $section . ') you are trying to access does not exist.');
        }
    }

    public function getExtension($name)
    {
        if (array_key_exists($name, $this->extensions)) {
            return $this->extensions[$name];
        } else {
            throw new \RuntimeException('The extension ("' . $name . '") you are trying to access does not exist. Registered extensions are: ' . implode(' ,', array_keys($this->extensions)) . '.');
        }
    }

    public function addExtension($name, $extension)
    {
        $this->extensions[$name] = $extension;
        $this->eventDispatcher->connectListener($extension);
    }

    public function getConfigArray()
    {
        return $this->configArray;
    }

    /**
     * @return HttpClient
     */
    public function getClient()
    {
        if (array_key_exists('client', $this->configArray)) {

            try {
                $client = Init::initialize($this->configArray['client']);
            } catch (\Exception $e) {
                throw new ConfigurationException('Error initializing client (' . $e->getMessage() . ')');
            }

            if (array_key_exists('cache', $this->configArray['client'])) {
                if ($this->configArray['client']['cache'] == true) {
                    $filesystemAdapter = new Local('/tmp/cached/');
                    $filesystem = new Filesystem($filesystemAdapter);
                    $cachePoolInterface = new FilesystemCachePool($filesystem);
                    return new CacheDecorator($client, $cachePoolInterface);
                }
            }
            return $client;
        } else {
            return new GuzzleClient();
        }
    }
}
