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
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

abstract class AbstractUrlMigrateCommand extends \Symfony\Component\Console\Command\Command
{
    public $includeHostnameInRedirect = false;
    /**
     * @var string The path to the file to output the rewrites to
     */
    public $vOutputDir;

    /**
     * The server the rewrites are intended for
     *
     * Available values are 'apache' or 'nginx'
     *
     * @var string Rewrite output server type
     */
    public $vOutputServer = 'nginx';

    public function __construct()
    {
        parent::__construct();
    }

    protected function configure()
    {
        $this
            ->setDescription('Generate URL mappings')
            ->setDefinition([
                new InputArgument('csv-file', InputArgument::REQUIRED, 'The CSV file path'),
                new InputArgument('output-dir', InputArgument::OPTIONAL, 'Folder to save generated files to. Directory must be manually created beforehand', 'migrateUrls'),
                new InputOption('output-type', 'o', InputOption::VALUE_OPTIONAL,
                    'Output rewrites in "apache" or "ngnix" ', 'apache'),
                new InputOption('host-name-in-redirect', 'r', InputOption::VALUE_OPTIONAL, 'Should the host name be included in the generated URL? (true/false)', 'false')
            ]);
    }

    /**
     * The init method
     * The entry point to the script.
     */
    public function execute(InputInterface $input, OutputInterface $output)
    {
        $vLogMessage='';
        $this->vOutputServer = $input->getOption('output-type');
        $this->vOutputDir = $input->getArgument('output-dir');
        $this->includeHostnameInRedirect = ($input->getOption('host-name-in-redirect') == 'true');
        $aCSV = $this->loadCSV($input->getArgument('csv-file'), $this->getColumnDefinition());
        $oResult = $this->process($aCSV);
        $vLogMessage .='Processed ' . $oResult->totalCount . " records\n";
        $vLogMessage .=$oResult->succeededCount . ' records succeeded' . "\n";
        $vLogMessage .=$oResult->failedCount . ' records failed' . "\n";
        $this->generateRewritesFile($oResult->succeeded, $this->vOutputDir, $this->vOutputServer);
        $output->write($vLogMessage);
    }

    /**
     * @return array the column names of CSV file being imported
     */
    public abstract function getColumnDefinition();

    /**
     * @return string name of output file
     */
    public abstract function generateFileName();

    /**
     * Loops the matched urls generating the server specific rewrites into the $vOutput string
     *
     * @param array $aData
     * @param string $vFile
     * @param string $vServer
     * @return boolean returns true if writing the file succeeds otherwise returns false
     */
    public function generateRewritesFile($aData, $vOutputDir, $vServer = 'apache')
    {
        $vOutput = '';

        $vFile = $vOutputDir . DIRECTORY_SEPARATOR . 'rewrites-'.$this->generateFileName().'-'.date('Ymd') . '.conf';

        switch ($vServer) {
            case 'nginx' :
                $aUrlGroups = array();
                foreach ($aData as $oUrl) {

                    $aUrl = parse_url($oUrl->oldUrl);
                    $oUrlData = new \stdClass;
                    $oUrlData->query = isset($aUrl['query']) ? $aUrl['query'] : '';
                    $oUrlData->newUrl = $oUrl->newUrl;

                    $aUrlGroups[$aUrl['path']][] = $oUrlData;
                }

                foreach ($aUrlGroups as $vUrlGroupKey => $aUrlGroup) {
                    if (sizeof($aUrlGroup) == 1 && !$aUrlGroup[0]->query) {
                        $vOutput .= $this->generateNginxRewrite($vUrlGroupKey, $aUrlGroup[0]->newUrl);
                    } else {
                        $vOutput .= $this->generateNginxGroupRewrite($vUrlGroupKey, $aUrlGroup);
                    }
                }
                break;
            default :
                foreach ($aData as $oUrl) {
                    $vOutput .= $this->generateApacheRewrite($oUrl->oldUrl, $oUrl->newUrl);
                }
                break;
        }

        $result = file_put_contents($vFile, $vOutput);
        if ($result === false) {
            return false;
        } else {
            return true;
        }
    }

    /**
     * Generates a single apache rewrite
     *
     * @param string $vOldUrl
     * @param string $vNewUrl
     * @return string
     */
    public function generateApacheRewrite($vOldUrl, $vNewUrl)
    {
        $vRewrite = '';
        if ($vOldUrl != $vNewUrl) {
            $aUrl = parse_url($vOldUrl);
            $vQuery = isset($aUrl['query']) ? $aUrl['query'] : '';
            $vPath = str_replace('.', '\.', ltrim($aUrl['path'], '/'));
            if ($vQuery) $vRewrite .= 'RewriteCond %{QUERY_STRING} ^' . $vQuery . '$' . "\n";
            $vRewrite .= 'RewriteRule ^' . $vPath . '$ ' . $vNewUrl . '? [R=301,L]' . "\n";
        }
        return $vRewrite;
    }

    /**
     * Generates a single nginx rewrite
     *
     * @param string $vOldUrl
     * @param string $vNewUrl
     * @return string
     */
    public function generateNginxRewrite($vOldUrl, $vNewUrl)
    {
        if ($vOldUrl != $vNewUrl) return 'rewrite ' . $vOldUrl . ' ' . $vNewUrl . ' permanent;' . "\n";
        return '';
    }

    /**
     * generates a grouped nginx rewrite, this is called if multiple urls have the same base or if they have a query string
     *
     * @param string $vGroupKey
     * @param array $aGroup
     * @return string
     */
    public function generateNginxGroupRewrite($vGroupKey, $aGroup)
    {
        $vRewrite = '';
        $vRewrite .= 'location ~ ' . str_replace('.', '\.', $vGroupKey) . '$ {' . "\n";
        foreach ($aGroup as $oUrlItem) {
            if ($oUrlItem->query) $vRewrite .= "\t" . 'if ($args ~ ' . $oUrlItem->query . ') {' . "\n\t";
            $vRewrite .= "\t" . 'rewrite ^ ' . $oUrlItem->newUrl . '? permanent;' . "\n";
            if ($oUrlItem->query) $vRewrite .= "\t" . '}' . "\n";
        }
        $vRewrite .= '}' . "\n";
        return $vRewrite;
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
    abstract public function process($aCSV);

    /**
     * Loads a CSV file and turns it into a multidimensional associative array then returns the array
     *
     * @param string $vFile
     * @param array $aColumns
     * @param string $vDelimiter
     * @param int $iLength
     * @return array
     * @throws \Exception If the CSV fails to open
     */
    abstract public function loadCSV($vFile, $aColumns, $vDelimiter = ',', $iLength = 4096);
}