<?php
/**
 * @category    Aligent
 * @package     Aligent_Console
 * @copyright   Copyright (c) 2016 Aligent Consulting. (http://www.aligent.com.au)
 *
 * @author      Tom Zola <tom@aligent.com.au>
 */
namespace Aligent\UrlMigrate\Console\Command\UrlMigrateCommands;

class UrlToUrlMigrateCommand extends AbstractUrlMigrateCommand
{
    /**
     * @inheritdoc
     */
    public function getColumnDefinition()
    {
        return ['urlFrom', 'urlTo'];
    }

    public function generateFileName()
    {
        return 'rewrites-url2url-'.date('Ymd');
    }

    /**
     * @inheritdoc
     */
    protected function configure()
    {
        $this->setName('migrateurls:url2url')
             ->setDescription('Generate URL mappings for products');
        parent::configure();
    }

    /**
     * Loops the CSV, looks up the product id based from the sku and tries to match a result from the rewrite table
     * if it finds an id of a product from the products table but doesn't find a matching url in the rewrite it
     * generates a default url for the product instead.
     *
     * Once the loop completes an object is returned with counts for succeeded, failed and total records
     * as well as two arrays, one for successful matches and one for failed matches.
     *
     * Each of these arrays contains objects with the product sku, id, old url, new url and an error message if there was one.
     *
     * @param array $aCSV
     * @param array $aRewriteData
     * @return \stdClass
     */
    public function process($aCSV)
    {
        $aSucceeded = array();
        $aFailed = array();

        foreach ($aCSV as $urlMapping) {
            $oUrl = new \stdClass;
            $oUrl->oldUrl = $urlMapping['urlFrom'];
            $oUrl->newUrl = $urlMapping['urlTo'];
            if(!$this->includeHostnameInRedirect) {
                $oUrl->newUrl = parse_url($oUrl->newUrl, PHP_URL_PATH);
                $oUrl->oldUrl = parse_url($oUrl->oldUrl, PHP_URL_PATH);
            }
            $aSucceeded[] = $oUrl;
        }

        $oResult = new \stdClass();
        $oResult->succeededCount = count($aSucceeded);
        $oResult->failedCount = count($aFailed);
        $oResult->failed = 0;
        $oResult->totalCount = $oResult->succeededCount;
        $oResult->succeeded = $aSucceeded;
        $oResult->failed = $aFailed;

        return $oResult;
    }

    /**
     * Loads a CSV file and turns it into a multidimensional associative array then returns the array
     *
     * @param string $vFile
     * @param array $aColumns
     * @param string $vDelimiter
     * @param int $iLength
     * @return array
     * @throws
     * \Exception If the CSV fails to open
     */
    public function loadCSV($vFile, $aColumns, $vDelimiter = ',', $iLength = 4096)
    {
        $oHandle = fopen($vFile, 'r');
        $aCSV = [];
        $iColumnSize = sizeof($aColumns);
        if($oHandle !== false) {
            while (($aData = fgetcsv($oHandle, $iLength, $vDelimiter)) !== false) {
                $urlMapping = [];
                for ($i = 0 ; $i < $iColumnSize ; $i ++) {
                    $urlMapping[$aColumns[$i]] = $aData[$i];
                }
                $aCSV[] = $urlMapping;
            }
            fclose($oHandle);
            return $aCSV;
        } else {
            throw new \Exception('Failed to open CSV');
        }
    }
}