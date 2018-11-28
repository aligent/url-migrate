<?php
/**
 * @category    Aligent
 * @package     Aligent_Console
 * @copyright   Copyright (c) 2017 Aligent Consulting. (http://www.aligent.com.au)
 *
 * @author      Phirun Son <phirun@aligent.com.au>
 */
namespace Aligent\UrlMigrate\Console\Command;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\CatalogUrlRewrite\Model\ProductUrlPathGenerator;
use Magento\Eav\Model\AttributeRepository;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\App\ResourceConnection;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\UrlRewrite\Model\UrlPersistInterface;
use Magento\CatalogUrlRewrite\Model\ProductUrlRewriteGenerator;
use Magento\UrlRewrite\Service\V1\Data\UrlRewrite;

class RegenerateUrlKeysCommand extends Command
{

    const CHUNK_SIZE = 100;
    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @var ProductRepositoryInterface
     */
    protected $productRepository;


    /**
     * @var ProductUrlRewriteGenerator
     */
    protected $productUrlRewriteGenerator;

    /**
     * @var UrlPersistInterface
     */
    protected $urlPersist;

    /**
     * @var ProductUrlPathGenerator
     */
    protected $productUrlPathGenerator;

    /**
     * @var ResourceConnection
     */
    protected $resource;

    /**
     * @var AttributeRepository
     */
    protected $attributeRepository;

    /**
     * @var SearchCriteriaBuilder
     */
    protected $searchCriteriaBuilder;

    /**
     * @param StoreManagerInterface $storeManager
     * @param ProductRepositoryInterface $productRepository
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param ProductUrlRewriteGenerator $productUrlRewriteGenerator
     * @param ProductUrlPathGenerator $productUrlPathGenerator
     * @param UrlPersistInterface $urlPersist
     * @param ResourceConnection $resource
     * @param AttributeRepository $attributeRepository
     */
    public function __construct(
        StoreManagerInterface $storeManager,
        ProductRepositoryInterface $productRepository,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        ProductUrlRewriteGenerator $productUrlRewriteGenerator,
        ProductUrlPathGenerator $productUrlPathGenerator,
        UrlPersistInterface $urlPersist,
        ResourceConnection $resource,
        AttributeRepository $attributeRepository
    ) {
        parent::__construct();
        $this->storeManager = $storeManager;
        $this->productRepository = $productRepository;
        $this->productUrlRewriteGenerator = $productUrlRewriteGenerator;
        $this->productUrlPathGenerator = $productUrlPathGenerator;
        $this->urlPersist = $urlPersist;
        $this->resource = $resource;
        $this->attributeRepository = $attributeRepository;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
    }

    protected function configure()
    {
        $this->setName('aligent:url:regenerate')->setDescription('Regenerate any urls that are mismatched between url_key and url rewrite table');
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        //Set to admin store
        $this->storeManager->setCurrentStore(0);
        $productIds = array_unique(array_merge($this->getMismatchedProductIds(), $this->getMissingProductIds()));
        $productIdChunks = array_chunk($productIds, self::CHUNK_SIZE);

        foreach ($productIdChunks as $productIdChunk) {
            $products = $this->getProducts($productIdChunk)->getItems();
            foreach ($products as $product) {
                try {
                    $this->urlPersist->deleteByData([
                        UrlRewrite::ENTITY_ID => $product->getId(),
                        UrlRewrite::ENTITY_TYPE => ProductUrlRewriteGenerator::ENTITY_TYPE
                    ]);

                    try {
                        $generated = $this->productUrlRewriteGenerator->generate($product);
                        $this->urlPersist->replace($generated);
                    } catch (\Exception $e) {
                        $output->writeln("<error>Duplicated url for {$product->getSku()}</error>");
                    }
                } catch (\Exception $e) {
                    $output->writeln("<error>Error for {$product->getSku()}: {$e->getMessage()}</error>");
                }
            }
        }
    }

    protected function getMismatchedProductIds()
    {
        $attribute = $this->attributeRepository->get('catalog_product', 'url_key');
        //Custom sql to get list of products that have a mismatched url key
        $conn = $this->resource->getConnection();
        $select = $conn->select()->distinct()->from(['e' => $conn->getTableName('catalog_product_entity')], 'entity_id')
            ->joinLeft(['u' => $conn->getTableName('url_rewrite')],'e.entity_id = u.entity_id AND u.entity_type = "product"', [])
            ->join(['v' => $conn->getTableName('catalog_product_entity_varchar')], 'e.row_id = v.row_id AND v.attribute_id = ' . $attribute->getAttributeId(), [])
            ->where(new \Zend_Db_Expr('RIGHT(u.request_path, LENGTH(CONCAT(v.value, ".html"))) <> CONCAT(v.value, ".html")'))
            ->where('e.type_id = ?', 'configurable');
        $productIds = $conn->fetchCol($select);
        return $productIds;
    }

    protected function getMissingProductIds()
    {
        //Custom sql to get list of products that have a missing url key
        $conn = $this->resource->getConnection();
        $subQuery = $conn->select()->distinct()->from($conn->getTableName('url_rewrite'), 'entity_id');

        $select = $conn->select()->distinct()->from(['e' => $conn->getTableName('catalog_product_entity')], 'entity_id')
            ->where(new \Zend_Db_Expr('e.entity_id NOT IN (' . $subQuery .')'))
            ->where('e.type_id = ?', 'configurable');
        $productIds = $conn->fetchCol($select);
        return $productIds;
    }

    protected function getProducts($productIds) {
        $searchCriteria = $this->searchCriteriaBuilder
            ->addFilter('entity_id', $productIds, 'in')
            ->create();
        $products = $this->productRepository->getList($searchCriteria);
        return $products;
    }
}
