<?php
/**
 * @category    Aligent
 * @package     Aligent_StoreLocator
 * @copyright   Copyright (c) 2016 Aligent Consulting. (http://www.aligent.com.au)
 *
 * @author      Phirun Son <phirun@aligent.com.au>
 */
namespace Aligent\UrlMigrate\Console\Command;

use Magento\Framework\Encryption\Encryptor;
use Magento\Framework\Exception\LocalizedException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Config\Model\ResourceModel\Config as ResourceConfig;

class ConfigSetCommand extends Command
{

    protected $scopeValues = ['default', 'websites', 'stores'];

    /**
     * @var array
     */
    protected $scopeIds;

    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @var ResourceConfig
     */
    protected $resourceConfig;

    /**
     * @var Encryptor
     */
    protected $encryptor;

    /**
     * @param StoreManagerInterface $storeManager
     * @param ResourceConfig $resourceConfig
     * @param Encryptor $encryptor
     */
    public function __construct(
        StoreManagerInterface $storeManager,
        ResourceConfig $resourceConfig,
        Encryptor $encryptor
    ) {
        $this->storeManager = $storeManager;
        $this->resourceConfig = $resourceConfig;
        $this->encryptor = $encryptor;
        parent::__construct();
    }

    protected function configure()
    {
        $this
            ->setName('config:set')
            ->setDescription('Set config value')
            ->setDefinition([
                new InputArgument('path', InputArgument::REQUIRED, 'The config path'),
                new InputArgument('value', InputArgument::REQUIRED, 'The config value'),
                new InputOption('scope', 's', InputOption::VALUE_OPTIONAL,
                    'The config value\'s scope (default, websites, stores)', 'default'),
                new InputOption('scope-id', 'id', InputOption::VALUE_OPTIONAL, 'The config value\'s scope ID', '0'),
                new InputOption('scope-code', 'code', InputOption::VALUE_OPTIONAL,
                    'The config value\'s scope code. If specified, will override the scope ID'),
                new InputOption('encrypt', 'enc', InputOption::VALUE_NONE,
                    'The config value should be encrypted using crypt key')
            ]);
    }

    protected function validateScope($scope)
    {
        if (!in_array($scope, $this->scopeValues)) {
            throw new LocalizedException(__('Invalid scope value: ' . $scope . '. Must be one of: (' . implode(', ',
                    $this->scopeValues) . ')'));
        }
    }

    public function getScopeByCode($scope, $code)
    {
        if ($this->scopeIds === null) {
            $websites = $this->storeManager->getWebsites(false, true);
            $stores = $this->storeManager->getStores(false, true);
            $this->scopeIds = ['websites' => $websites, 'stores' => $stores];
        }

        return $this->scopeIds[$scope][$code];
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        try {
            $path = $input->getArgument('path');
            $value = $input->getArgument('value');
            $scope = $input->getOption('scope');
            $this->validateScope($scope);

            $scopeId = $input->getOption('scope-id');
            $scopeCode = $input->getOption('scope-code');
            if ($scopeCode !== null) {
                $scopeObject = $this->getScopeByCode($scope, $scopeCode);
                $scopeId = $scopeObject->getId();
            }

            $enc = $input->getOption('encrypt');

            if ($enc) {
                $value = $this->encryptor->encrypt($value);
            }

            $this->resourceConfig->saveConfig($path, $value, $scope, $scopeId);
            $output->writeln("$path => $value");
        } catch (\Exception $e) {
            $output->writeln('Unable to write configuration: ' . $e->getMessage());
        }
    }
}
