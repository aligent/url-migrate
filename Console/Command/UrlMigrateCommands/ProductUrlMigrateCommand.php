<?php
/**
 * @category    Aligent
 * @package     Aligent_Console
 * @copyright   Copyright (c) 2016 Aligent Consulting. (http://www.aligent.com.au)
 *
 * @author      Tom Zola <tom@aligent.com.au>
 */
namespace Aligent\UrlMigrate\Console\Command\UrlMigrateCommands;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;

class ProductUrlMigrateCommand extends AbstractUrlMigrateCommand
{
    /**
     * @var int id of the store being mapped
     */
    protected $iStoreId;

    /**
     * @var \Magento\Store\Model\Store store that is being mapped to
     */
    protected $oStore;

    /**
     * @var string code of store that is being mapped to
     */
    public $vStoreCode;

    /**
     * @var string The path to the file to output the rewrites to
     */
    public $vOutputDir;

    /**
     * @var \Magento\Store\Model\StoreManagerInterface for loading store
     */
    protected $storeManager;

    /**
     * @var  \Magento\Catalog\Model\ProductFactory for loading products
     */
    protected $productFactory;

    /**
     * @var \Magento\Store\Model\App\Emulation
     */
    protected $appEmulation;


    public function __construct(\Magento\Store\Model\StoreManagerInterface $storeManager,
                                \Magento\Catalog\Model\ProductFactory $productFactory,
                                \Magento\Store\Model\App\Emulation $emulation,
                                \Magento\Framework\App\State $state)
    {
        $this->storeManager = $storeManager;
        $this->productFactory = $productFactory;
        $this->appEmulation = $emulation;
        $state->setAreaCode(\Magento\Framework\App\Area::AREA_ADMINHTML); // App emulation fails if the app does not have an existing area code prior to emulation.
        parent::__construct();
    }

    /**
     * @inheritdoc
     */
    public function getColumnDefinition()
    {
        return array('sku', 'url');
    }

    /**
     * @inheritdoc
     */
    protected function configure()
    {
        parent::configure();
        $this->getDefinition()->addOption(new InputOption('store-scope-code', 's', InputOption::VALUE_REQUIRED, 'The store scope ID to map product URLs to', '0'));
        $this->setName('migrateurls:catalog:product')
            ->setDescription('Generate URL mappings for products');
    }

    /**
     * @inheritdoc
     */
    public function generateFileName()
    {
        return 'rewrites-'.$this->oStore->getCode().'-'.date('Ymd');
    }

    /**
     * The init method
     * The entry point to the script.
     */
    public function execute(InputInterface $input, OutputInterface $output)
    {
        $this->getStore($input->getOption('store-scope-code'));
        parent::execute($input, $output);
    }

    protected function getStore($vStoreCode) {
        $this->vStoreCode = $vStoreCode;
        $this->oStore = $this->storeManager->getStore($this->vStoreCode);
        $this->iStoreId = $this->oStore->getId();
    }

    /**
     * @inheritdoc
     */
    public function process($aCSV)
    {
        $aSucceeded = array();
        $aFailed = array();

        $vErrorMessage='Could not find a record from the database matching the SKU/SKUs'."\n";
        $generated = 0;

        $this->appEmulation->startEnvironmentEmulation($this->iStoreId, \Magento\Framework\App\Area::AREA_FRONTEND);
        foreach ($aCSV as $aRow) {
            if (isset($aRow['sku']) && isset($aRow['url'])) {
                $oProduct = $this->productFactory->create()->loadByAttribute('sku', $aRow['sku']);
                if($oProduct && $oProduct->getId()) {
                    $oUrl = new \stdClass;
                    $oUrl->sku = $aRow['sku'];
                    $oUrl->oldUrl = $aRow['url'];
                    try {
                        $vErrorMessage .= '' . $oUrl->sku . "\n";
                        $oUrl->productId = $oProduct->getId();
                        $oUrl->newUrl = $oProduct->getUrlModel()->getUrl($oProduct);
                        if (!$this->includeHostnameInRedirect) {
                            $oUrl->newUrl = parse_url($oUrl->newUrl, PHP_URL_PATH);
                        }
                        $aSucceeded[$aRow['url']] = $oUrl;
                        $generated++;
                    } catch (\Magento\Framework\Exception\NoSuchEntityException $e) {
                        $aFailed[$aRow['url']] = $oUrl;
                        $oUrl->error = 'Could not find a record from the database matching the SKU' . $oUrl->sku . ' from CSV';
                    }
                }
            }
        }
        $this->appEmulation->stopEnvironmentEmulation();

        $oResult = new \stdClass();
        $oResult->succeededCount = count($aSucceeded);
        $oResult->failedCount = count($aFailed);
        $oResult->failed = $aFailed;
        $oResult->totalCount = $oResult->succeededCount + $oResult->failedCount;
        $oResult->succeeded = $aSucceeded;
        $oResult->failed = $aFailed;

        return $oResult;
    }

    /**
     * @inheritdoc
     */
    public function loadCSV($vFile, $aColumns, $vDelimiter = ',', $iLength = 4096)
    {
        $oHandle = fopen($vFile, 'r');
        $aCSV = [];
        $iColumnSize = sizeof($aColumns);
        if($oHandle !== false) {
            while (($aData = fgetcsv($oHandle, $iLength, $vDelimiter)) !== false) {
                $aProduct = [];
                for ($i = 0 ; $i < $iColumnSize ; $i ++) {
                    $aProduct[$aColumns[$i]] = $aData[$i];
                }
                $aCSV[] = $aProduct;
            }
            fclose($oHandle);
            return $aCSV;
        } else {
            throw new \Exception('Failed to open CSV');
        }
    }
}
