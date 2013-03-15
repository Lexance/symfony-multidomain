<?php

/*
 * (c) Victor J. C. Geyer <victorgeyer@ciscaja.com>
 */

use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\HttpKernel\Kernel;

/**
 * In the spirit of OOP, this class extends the Symfony2 native Kernel class
 * and make Symfony2 multi-domainable.
 *
 * @author Victor J. C. Geyer <victorgeyer@ciscaja.com>
 */
class MultiDomainKernel extends Kernel 
{
    /**
     * Holds the server environment without the name prefix(sauerkraut)
     * 
     * @var string 
     */
    protected $server_environment = null;
    
    /**
     * Holds the server name without the environment postfix(_dev)
     * 
     * @var string 
     */
    protected $server_name = null;
    
    /**
     * @var bool 
     */
    protected $server_debug = null;

    /**
     * Holds the loaded config from the loaded config file.
     * 
     * @var array
     */
    protected $server_config = null;
    
    /**
     * @var array
     */
    protected $defaults = array();
    
    /**
     * {@inheritdoc}
     */
    public function __construct($environment, $debug) {
        $this->defaults = $this->getDefaultEnvironment();
        $this->determineDefaults($environment, $debug);
        if(\php_sapi_name() !== 'cli')
            $this->useServerConfigFile();

        $this->setServerName(($this->getServerName() === null) ? $this->defaults['name'] : $this->getServerName());
        $this->setServerEnvironment(($this->getServerEnvironment() === null) ? $this->defaults['environment'] : $this->getServerEnvironment());
        $this->setServerDebug(($this->getServerDebug() === null) ? $this->defaults['debug'] : $this->getServerDebug());

        $environment = $this->getServerName().'_'.$this->getServerEnvironment();
        $debug = $this->getServerDebug();

        parent::__construct($environment, $debug);
    }
    
    /**
     * Builds the default server environment, name and debug mode.
     * 
     * @param string  $environment The environment
     * @param Boolean $debug       Whether to enable debugging or not
     */
    protected function determineDefaults($environment, $debug) {
        $env_splitted = array();
        $this->defaults['debug'] = $debug;
        $regex = \implode("|", $this->getServerEnvironments());
        
        \preg_match('/(.*)_('.$regex.')/', $environment, $env_splitted);
        //if something like 'sauerkraut_dev' is passed..
        if(\count($env_splitted) > 2) {
            $this->defaults['name'] = $env_splitted[1];
            $this->defaults['environment'] = $env_splitted[2];
        } else {
            //if only 'dev' or 'prod' is passed
            if(\in_array($environment, $this->getServerEnvironments())) {
                $this->defaults['environment'] = $environment;
            } else {
                //if something like 'sauerkraut' is passed...
                $this->defaults['name'] = $environment;
            }
        }
    } 

    
    /**
     * Loads& decodes the config and set the current server name& environment.
     * 
     * @return boolean FALSE when there was an processing or loading error.
     */
    public function useServerConfigFile() {
        if(!\file_exists($this->getServerConfigFile()))
            return false;

        $server_config = \json_decode(\file_get_contents($this->getServerConfigFile()), true);
        
        if($server_config === null || $this->normalizeServerConfig($server_config) === false)
            die("Your ".$this->getServerConfigFile()." is malformed please fix or remove it.");
        
        if($this->setServerEnvironment($server_config['environments']) === false || $this->setServerName($server_config['names']) === false)
           return false;

        return true;
    }

    /**
     * @param $server_config
     *
     * @return bool
     */
    protected function normalizeServerConfig (&$server_config) {
        //server environments...
        if(!isset($server_config['environments']) || !is_array($server_config['environments']))
            $server_config['environments'] = array();
        
        foreach($server_config['environments'] as &$settings) {
            if(!\is_array($settings)) {
                if(!\is_string($settings))
                    return false;
                $settings = array('name' => $settings);
            }
        }
        
        //server names..
        if(!isset($server_config['names']) || !\is_array($server_config['names']))
            $server_config['names'] = array();
        
        foreach($server_config['names'] as &$domains) {
            if(!\is_array($domains) && !\is_string($domains))
                return false;
            if(\is_string($domains))
                $domains = array($domains);
        }

        //fallback != defaults!
        if(isset($server_config['fallback']) && \is_array($server_config['fallback']))
            $this->defaults = \array_merge($this->defaults, $server_config['fallback']);

        return true;
    }
    
    /**
     * Determinates and sets the server name by the $_SERVER['SERVER_NAME'] var.
     * 
     * @param array $config_names
     * @return bool FALSE if there was no match with the config.
     */
    public function setServerName($config_names)
    {
        if(!\is_string($config_names)) {
            $server_name = $_SERVER['SERVER_NAME'];
                foreach($config_names as $name => $domains) {
                    foreach($domains as $domain) {
                        if($server_name === $domain) {
                            $this->server_name = $name;
                            return true;
                        }
                    }
                }
            return false;
        } else {
            $this->server_name = $config_names;
        }
    }
    
    /**
     * Determinates and sets the server environment and server debug mode by 
     * the $_SERVER['SERVER_PORT'] var.
     * 
     * @param array|string $config_environments
     * @return bool FALSE if there was no match with the config.
     */
    public function setServerEnvironment($config_environments) 
    {
        if(!\is_string($config_environments)) {
            $server_port = (int) $_SERVER['SERVER_PORT'];
            foreach($config_environments as $port => $environment) {
                if($server_port === ((int) $port)) {
                    $this->server_environment = $environment['name'];
                    if(isset($environment['debug']))
                        $this->setServerDebug ($environment['debug']);
                    return true;
                }
            }
            return false;
        } else {
            $this->server_environment = $config_environments;
        }
    }

    /**
     * @param $config_debug
     */
    public function setServerDebug($config_debug) {
        if($config_debug === true || strtolower($config_debug) === 'true')
            $this->server_debug = true;
        else
            $this->server_debug = false;
    }

    /**
     * @return string
     */
    public function getServerConfigFile() {
        return $this->getRootDir()."/config/server.json";
    }

    /**
     * @return null|string
     */
    public function getServerEnvironment() 
    {
        return $this->server_environment;
    }

    /**
     * @return null|string
     */
    public function getServerName() 
    {
        return $this->server_name;
    }


    /**
     * @return bool|null
     */
    public function getServerDebug() {
       return $this->server_debug;
    }

    /**
     * {@inheritdoc}
     */
    public function getCacheDir()
    {
        return $this->rootDir.'/cache/'.$this->getServerName().'/'.$this->getServerEnvironment();
    }

    /**
     * @return array
     */
    public function registerBundles() {
        return array();
    }
    
    /**
     * @param \Symfony\Component\Config\Loader\LoaderInterface $loader
     */
    public function registerContainerConfiguration(\Symfony\Component\Config\Loader\LoaderInterface $loader)
    {
    }
    
    /**
     * {@inheritdoc}
     * 
     * Lets face it. We need the server name for importing the routing.yml in the 
     * config because the router cant handle relative paths. And I hate Copy& Paste.
     * Fore the sake of completeness we also setting the server environment.
     */
    protected function getKernelParameters()
    {
        //get the original params
        $parameters = parent::getKernelParameters();
        
        //and merge it with ours.
        return \array_merge(
            array(
                'kernel.server_name' => $this->getServerName(),
                'kernel.server_environment' => $this->getServerEnvironment(),
            ),
            $parameters 
        );
    }
}