<?php
/**
 * @category    Aligent
 * @package     Aligent_StoreLocator
 * @copyright   Copyright (c) 2016 Aligent Consulting. (http://www.aligent.com.au)
 *
 * @author      Phirun Son <phirun@aligent.com.au>
 */
namespace Aligent\UrlMigrate\Console\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Magento\Store\Model\StoreManagerInterface;

class EnvironmentCommand extends Command
{
    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(StoreManagerInterface $storeManager)
    {
        $this->storeManager = $storeManager;
        parent::__construct();
    }

    protected function configure()
    {
        $this->setName('info:environment')->setDescription('Show current environment');
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        $baseUrl = $this->storeManager->getDefaultStoreView()->getBaseUrl();
        $output->writeln($baseUrl);
    }
}
