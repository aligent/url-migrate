# Product URL Migrater

The following Magento console command will take in a CSV containing product sku and current URL and output a webserver configuration containing url rewrites.

The script will:
- loop through the CSV
- look up the product id based on the sku and try to match a result from the rewrite table
- If it finds a product but doesn't find a matching url it will rewrite it

## Example Command:

`bin/magento migrateurls:catalog:product catalog_product.csv .`
```
Arguments:
  csv-file                                             The CSV file path
  output-dir                                           Folder to save generated files to. Directory must be manually created beforehand [default: "migrateUrls"]

Options:
  -o, --output-type[=OUTPUT-TYPE]                      Output rewrites in "apache" or "ngnix"  [default: "apache"]
  -r, --host-name-in-redirect[=HOST-NAME-IN-REDIRECT]  Should the host name be included in the generated URL? (true/false) [default: "false"]
  -s, --store-scope-code=STORE-SCOPE-CODE              The store scope ID to map product URLs to [default: "0"]
  -h, --help                                           Display this help message
  -q, --quiet                                          Do not output any message
  -V, --version                                        Display this application version
      --ansi                                           Force ANSI output
      --no-ansi                                        Disable ANSI output
  -n, --no-interaction                                 Do not ask any interactive question
  -v|vv|vvv, --verbose                                 Increase the verbosity of messages: 1 for normal output, 2 for more verbose output and 3 for debug
```





