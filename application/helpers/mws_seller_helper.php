<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of MWS_Seller
 *
 * @author 
 */
require_once(APPPATH . 'libraries/phpAmazonMWS/includes/includes.php');

$MWS_seller_id = '';
$MWS_marketplace_id = '';
$MWS_auth_token = '';

class MWSLogs extends AmazonCore {

    function __construct($s = null, $mock = false, $m = null, $config = null) {
        parent::__construct($s, $mock, $m, $config);
    }

}

class MWS_Seller {

    //put your code here
    private $db, $db_mysql;
    private $seller;
    private $currency_code;
    private $tmp_path;
    public $mws_logs;

    /**
     * Initialize MWS_Seller with correct credentials
     * communitcate with Amazon
     */
    function __construct($seller_id, $marketplace_id = null) {
        $this->mws_logs = new MWSLogs();

        $this->log('Initializing seller..' . $seller_id);
        $CI = & get_instance();
        $this->CI = $CI;
        $this->db = $CI->db;
        $this->db_mysql = $CI->db_mysql;
        if ($marketplace_id == null) {
            $marketplace_id = MARKETPLACE_ID;
        }
        $conditons = array("sellerid" => $seller_id, "marketplaceid" => $marketplace_id);
        $this->seller = $this->db->get_where("user_settings", $conditons)->row_array();
        if (!$this->seller) {
            throw new Exception("Seller $seller_id not found in " . $marketplace_id . ' Market Place');
        }

        $this->currency_code = $this->CI->config->item($this->getMarketPlaceId(), 'gl_currency');
        $this->tmp_path = sys_get_temp_dir();
    }
    function isAuthrized(){
        $this->setStore();
        $amz = new AmazonParticipationList();
        $amz->fetchParticipationList();
        $part = $amz->getParticipationList();
        if( count($part) == 0 ){
            $this->log('*****NOT Authorized******');
            return false;
        }
        $this->log('Authorized');
        return true;
    }
    function log($message, $level = null) {
        if (!$level) {
            $level = 'info';
        }
        $datetime = date('Y-m-d H:i:s');
        $sellerid = $this->seller['sellerid'];
        cli_echo("[$datetime] - $sellerid - " . $message);
        $this->mws_logs->log($sellerid . ' - ' . $message, $level);
    }

    function getSinceUpdatedMins() {
        $sellerid = $this->getSellerId();
        $query = "SELECT TIMESTAMPDIFF(MINUTE, last_modified, now()) minutes FROM user_settings WHERE sellerid='{$sellerid}'";
        $result = $this->db->query($query)->row_array();
        return $result['minutes'];
    }

    function updateLastModified() {
        $sellerid = $this->getSellerId();
        $query = "UPDATE user_settings SET last_modified=now() WHERE sellerid='{$sellerid}'";
        $this->db->query($query)->row_array();
    }

    function getSellerId() {
        return $this->seller['sellerid'];
    }

    function getEmail() {
        return $this->seller['email'];
    }

    function getMarketPlaceId() {
        return $this->seller['marketplaceid'];
    }

    /**
     * Get local listing from database
     * 
     */
    function getListings($status = null) {
        $this->log('Fetching seller listings');
        $conditions = array(
            "sellerid" => $this->seller['sellerid'],
            "marketplaceid" => $this->seller['marketplaceid']
        );

        if ($status) {
            $conditions['status'] = $status;
        }
        
        $listings = $this->db->get_where("user_listings", $conditions)->result_array();
        $results = array();
        foreach ($listings as $row) {
            $results[$row['sku']] = $row;
        }
        return $results;
    }

    /**
     * Get local listing from database
     * 
     */
    function getNewListings() {
        $this->log('Fetching seller new listings');
        $conditions = array(
            "sellerid" => $this->seller['sellerid'],
            "marketplaceid" => $this->seller['marketplaceid']
        );

        $conditions['last_repriced'] = NULL;
//        $conditions['sku'] = 'DW-GSJD-B67U';
        $listings = $this->db->get_where("user_listings", $conditions)->result_array();
        $results = array();
        foreach ($listings as $row) {
            $results[$row['sku']] = $row;
        }
        return $results;
    }

    /**
     * Get local product from database by sku
     * 
     */
    function getProductBySKU($sku) {
        
    }

    /**
     * Set seller credentials
     */
    private function setStore() {
        //$this->seller->sellerid,$this->seller->marketplaceid
        global $MWS_seller_id, $MWS_marketplace_id, $MWS_auth_token;
        $MWS_seller_id = $this->seller['sellerid'];
        $MWS_marketplace_id = $this->seller['marketplaceid'];
        $MWS_auth_token = $this->seller['mwsauthtoken'];
    }

    /**
     * Get products from amazon based on SKUs
     */
    function getMWSProducts($skuArray) {
        if (!is_array($skuArray)) {
            $skuArray = array($skuArray);
        }
        $this->setStore();
        $products = array();
        $amz = new AmazonProductList();

        $limit = 5;
        $start = 0;
        while ($skuList = array_slice($skuArray, $start, $limit)) {
            $amz->setProductIds($skuList);
            $amz->setIdType("SellerSKU");
            $amz->fetchProductList();
            $products += $amz->getProduct();
            $start += $limit;
        }
        return $products;
    }

    /**
     * Get products Offer, LowestOffer and CompetitivePricing from amazon based on SKUs
     */
    function MWSProductListingData($skuArray) {
        $this->setStore();
        $results = array();
        $limit = 20;
        $start = 0;
        $count = count($skuArray);
        $this->log("Fetching listing $count from amazon...");
        while ($skuList = array_slice($skuArray, $start, $limit)) {
            $this->log("Fetching listing {$start} of {$count} from amazon...");
            // get my pricing
            $amz = new AmazonProductInfo();
            $amz->setSKUs($skuList);
            $this->log('fetchMyPrice for ' . implode(', ', $skuList));
            $amz->fetchMyPrice();
            $products = $amz->getProduct();
            foreach ($products as $product) {
                if (!is_object($product)) {
                    $this->log('not a product');
                    continue;
                }
                $tmp = $product->getData();
                $results[$tmp['Identifiers']['SKUIdentifier']['SellerSKU']] = $tmp;
                if (!isset($tmp['Offers'])) {
                    $results[$tmp['Identifiers']['SKUIdentifier']['SellerSKU']]['Offers'] = null;
                    $results[$tmp['Identifiers']['SKUIdentifier']['SellerSKU']]['qty'] = 0;
                } else {
//                    debug($results[$tmp['Identifiers']['SKUIdentifier']['SellerSKU']]['Offers']);
                }
            }
            // get competitive pricing
            $amz = new AmazonProductInfo();
            $amz->setSKUs($skuList);
            $this->log('fetchCompetitivePricing for ' . implode(', ', $skuList));
            $amz->fetchCompetitivePricing();
            $products = $amz->getProduct();
            foreach ($products as $product) {
                $tmp = $product->getData();
                $results[$tmp['Identifiers']['SKUIdentifier']['SellerSKU']]['CompetitivePricing'] = $tmp['CompetitivePricing'];
            }

            // get competitive pricing
            $amz = new AmazonProductInfo();
            $amz->setSKUs($skuList);
            $this->log('fetchLowestOffer for ' . implode(', ', $skuList));
            $amz->fetchLowestOffer();
            $products = $amz->getProduct();
            foreach ($products as $product) {
                $tmp = $product->getData();
//                debug($tmp);exit();
                $results[$tmp['Identifiers']['SKUIdentifier']['SellerSKU']]['LowestOfferListings'] = $tmp['LowestOfferListings'];
            }

            // get inventory
            $amz = new AmazonInventoryList();
            $amz->setSellerSkus($skuList);
            $this->log('fetching inventory for ' . implode(', ', $skuList));
            $amz->fetchInventoryList();
            $supplies = $amz->getSupply();
            foreach ($supplies as $supply) {
                if (isset($results[$supply['SellerSKU']]['Offers'][0]['FulfillmentChannel']) && $results[$supply['SellerSKU']]['Offers'][0]['FulfillmentChannel'] != 'MERCHANT') {
                    $results[$supply['SellerSKU']]['qty'] = !empty($supply['InStockSupplyQuantity']) ? $supply['InStockSupplyQuantity'] : 0;
//                    debug($results[$supply['SellerSKU']]);
                } else {
                    //debug($results[$supply['SellerSKU']]['Offers']);
                }
            }


            $start += $limit;
        }
        return $results;
    }

    /**
     * Get inventory data based on time duration
     */
    function getMWSInvetory($since) {
        $this->setStore();
        $mwsInvObj = new AmazonInventoryList();
        $mwsInvObj->setStartTime($since);
        $mwsInvObj->fetchInventoryList();
        $inventory = $mwsInvObj->getSupply();
        return $inventory;
    }

    function LocalUpdateProduct($data) {
        try {
            $tmp = $this->LocalGetProduct($data['sku']);
            $tmp['id'];
            $data['id'] = $tmp['id'];
        } catch (Exception $e) {
            // do nothing - new item will be inserted
        }

//        debug($data);
        unset($data['last_modified']);
//        debug($data);exit();
        $this->log('Updating local product ' . $data['sku']);
        $this->db_mysql->on_duplicate_key_update()->insert("user_listings", $data);
    }

    function LocalGetProduct($sku) {
        if (empty($sku)) {
            throw new Exception('SKU is empty');
        }
        $this->log('Fetching local product ' . $sku);
        $conditions = array(
            "sellerid" => $this->seller['sellerid'],
            "marketplaceid" => $this->seller['marketplaceid'],
            "sku" => $sku
        );

        $product = $this->db->get_where("user_listings", $conditions)->result_array();
//        debug($this->db);exit();
        if (empty($product)) {
            throw new Exception($sku . ' does not exist');
        }
        return $product[0];
    }

    function MWSGetProduct($sku) {
        $this->setStore();
        $mws_product = array();

        // get my pricing
        $amz = new AmazonProductInfo();
        $amz->setSKUs($sku);
        $this->log('fetchMyPrice for ' . $sku);
        $amz->fetchMyPrice();
        $product = $amz->getProduct()[0];
        if (!is_object($product)) {
//            debug($product);
            $this->log('not a product');
            return false;
        }
        $tmp = $product->getData();
        $mws_product = $tmp;
        if (!isset($tmp['Offers'])) {
            $mws_product['Offers'] = null;
            $mws_product['qty'] = 0;
        }
        // get competitive pricing
        $amz = new AmazonProductInfo();
        $amz->setSKUs($sku);
        $this->log('fetchCompetitivePricing for ' . $sku);
        $amz->fetchCompetitivePricing();
        $product = $amz->getProduct()[0];
        $tmp = $product->getData();
        $mws_product['CompetitivePricing'] = $tmp['CompetitivePricing'];

        // get competitive pricing
        $amz = new AmazonProductInfo();
        $amz->setSKUs($sku);
        $this->log('fetchLowestOffer for ' . $sku);
        $amz->fetchLowestOffer();
        $product = $amz->getProduct()[0];
        $tmp = $product->getData();
        $mws_product['LowestOfferListings'] = $tmp['LowestOfferListings'];

        if (isset($mws_product['Offers'][0]['FulfillmentChannel']) && $mws_product['Offers'][0]['FulfillmentChannel'] != 'MERCHANT') {
            $amz = new AmazonInventoryList();
            $amz->setSellerSkus($sku);
            $this->log('fetching inventory for ' . $sku);
            $amz->fetchInventoryList();
            $supply = $amz->getSupply()[0];
            $mws_product['qty'] = !empty($supply['InStockSupplyQuantity']) ? $supply['InStockSupplyQuantity'] : 0;
        }


        return $mws_product;
    }

    private function _MWSSubmitFeed($feed_type, $feed) {
        $this->setStore();
        $amz = new AmazonFeed();
        $amz->setMarketplaceIds($this->getMarketPlaceId());
        $amz->setFeedType($feed_type);
        $amz->setFeedContent($feed);
        $this->log("Submitting Feed " . $feed);
        $amz->submitFeed();
        $status = $amz->getResponse();
        debug($status);
        return $status;
    }

    function MWSShipPriceUpdate($sku, $ship_pirce) {
        $feed = '<?xml version="1.0" encoding="utf-8"?>
            <AmazonEnvelope xsi:noNamespaceSchemaLocation="amznenvelope. xsd" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"> 
            <Header> 
            <DocumentVersion>1.01</DocumentVersion> 
            <MerchantIdentifier>' . $this->seller['sellerid'] . '</MerchantIdentifier> 
            </Header> 
            <MessageType>Override</MessageType>
             <Message>
             <MessageID>1</MessageID>
             <OperationType>Update</OperationType>
             <Override>
            <SKU>' . $sku . '</SKU>
             <ShippingOverride>
             <ShipOption>Same US</ShipOption>
             <Type>Exclusive</Type>
            <ShipAmount currency="' . $this->currency_code . '">' . $ship_pirce . '</ShipAmount>
             </ShippingOverride>
             </Override>
             </Message>
            </AmazonEnvelope>';

        $response = $this->_MWSSubmitFeed(_POST_PRODUCT_OVERRIDES_DATA_, $feed);
        return $response;
    }

    function MWSPriceUpdate($sku, $price = null, $min_price = null, $max_price = null, $map_price = null) {
        $this->log("Updating $sku Price to $price");
        $stdPriceXML = '';
        $minPriceXML = '';
        $maxPriceXML = '';
        $mapPriceXML = '';

        if ($price && $price > 0) {
            $stdPriceXML = '<StandardPrice currency="' . $this->currency_code . '">' . $price . '</StandardPrice> ';
        }

        if ($min_price && $min_price > 0) {
            $minPriceXML = '<MinimumSellerAllowedPrice currency="' . $this->currency_code . '">' . $min_price . '</MinimumSellerAllowedPrice>';
        }

        if ($max_price && $max_price > 0) {
            $maxPriceXML = '<MaximumSellerAllowedPrice currency="' . $this->currency_code . '">' . $max_price . '</MaximumSellerAllowedPrice>';
        }

        if ($map_price && $map_price > 0) {
            $mapPriceXML = '<MAP currency="' . $this->currency_code . '">' . $map_price . '</MAP> ';
        }

        $feed = '<?xml version="1.0" encoding="UTF-8"?>
            <AmazonEnvelope xsi:noNamespaceSchemaLocation="amznenvelope. xsd" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"> 
            <Header> 
            <DocumentVersion>1.01</DocumentVersion> 
            <MerchantIdentifier>' . $this->seller['sellerid'] . '</MerchantIdentifier> 
            </Header> 
            <MessageType>Price</MessageType> 
            <Message> <MessageID>1</MessageID> 
            <Price> 
             <SKU>' . $sku . '</SKU>'
                . $stdPriceXML
                . $minPriceXML
                . $maxPriceXML
                . $mapPriceXML
                . '</Price> 
            </Message> 
            </AmazonEnvelope>';

        $response = $this->_MWSSubmitFeed(_POST_PRODUCT_PRICING_DATA_, $feed);
        return $response;
    }

    function MWSGetFeed($feed_id) {
        $this->setStore();
        $amz = new AmazonFeedList();
        $amz->setFeedIds($feed_id);
        $amz->fetchFeedSubmissions();
        $list = $amz->getFeedList();
        return $list;
    }

    private function _MWSGetReportID($request_id) {
//        $request_id = 50535016933;
        $this->setStore();
        $amz = new AmazonReportRequestList();
        $amz->setRequestIds($request_id);
        $amz->fetchRequestList();
        $list = $amz->getList();
        return $list;
    }

    private function _MWSRequestReport($type, $past_days = null) {
        $this->setStore();
        $amz = new AmazonReportRequest();
        $amz->setReportType($type);
        if ($past_days) {
            $s = date('Y-m-d\TH:i:s', strtotime("-$past_days days"));
            $amz->setTimeLimits($s);
        }
        $amz->requestReport();
        $response = $amz->getResponse();
        return $response;
    }

    private function _MWSGetReport($report_id) {
        $this->log("Fetching report " . $report_id);
        $this->setStore();
        $amz = new AmazonReport();
        $amz->setReportId($report_id);
        $amz->fetchReport();
        $path = $this->tmp_path . 'mws_report_' . $report_id . '.csv';
        $amz->saveReport($path);
        $fp = fopen($path, 'r');
        if (($headers = fgetcsv($fp, 0, "\t")) !== FALSE) {
            if ($headers) {
                while (($line = fgetcsv($fp, 0, "\t")) !== FALSE) {
                    if ($line) {
                        if (sizeof($line) == sizeof($headers)) {
                            $result[] = array_combine($headers, array_map("utf8_encode", $line));
                        }
                    }
                }
            }
        }
//        unlink($path);
        return $result;
    }

    function MWSGetReportByRequestID($request_id = null) {

        return $this->_MWSGetReportID($request_id);
    }

    function MWSGetReport($type, $past_days = null) {
        $this->log("Requesting report " . $type);
        $r = $this->_MWSRequestReport($type, $past_days);
        if (!isset($r['ReportRequestId'])) {
            $this->log('ERROR: ' . $r['ReportProcessingStatus']);
        }
        $sleep_time = 20;
        $counter = 0;
        $this->log( ++$counter . ". Sleeping for $sleep_time seconds");
        sleep($sleep_time);
        while ($counter <= 5) {
            $this->log('Checking for Request ID ' . $r['ReportRequestId'], 'info');
            $result = $this->_MWSGetReportID($r['ReportRequestId']);
            $report = null;
            foreach ($result as $list) {
                if ($list['ReportRequestId'] == $r['ReportRequestId']) {
                    $report = $list;
                    break;
                }
            }

            if ($report['ReportProcessingStatus'] == '_CANCELLED_') {
                throw new Exception("Request Cancelled for " . $report['ReportRequestId']);
            }

            if (empty($report['GeneratedReportId'])) {
                $this->log( ++$counter . ". Sleeping for $sleep_time seconds");
                sleep($sleep_time);
                continue;
            }

            break;
        }
        if (empty($report['GeneratedReportId'])) {
            throw new Exception("Timedout for " . $report['ReportRequestId']);
            return false;
        }
        $report = $this->_MWSGetReport($report['GeneratedReportId']);
        $this->log(count($report) . " rows found in report");
        return $report;
    }

    function getMerchantListingsData() {
        try {
            $report = $this->MWSGetReport(_GET_MERCHANT_LISTINGS_DATA_, 1);
        } catch (Exception $e) {
            return false;
        }

        $skuList = array();
        foreach ($report as $row) {
            $new['sellerid'] = $this->getSellerId();
            $new['marketplaceid'] = $this->getMarketPlaceId();
            $new['itemname'] = htmlentities($row['item-name']);
            $new['listing_id'] = $row['listing-id'];
            $new['sku'] = $row['seller-sku'];
            $new['qty'] = $row['quantity'];
            $new['price'] = $row['price'];
            $new['marketplace'] = $row['item-is-marketplace'];
            list($tempcon, $tempsub) = parse_condition($row['item-condition']);
            $new['item_condition'] = $tempcon;
            $new['item_subcondition'] = $tempsub;
            $new['asin'] = $row['asin1'];
            $new['product_id'] = $row['product-id'];
            $new['product_id_type'] = $row['product-id-type'];
            $new['fulfillment_channel'] = $row['fulfillment-channel'];
            $new['prevprice'] = $row['price'];
            $new['email'] = $this->getEmail();

            $skuList[$row['seller-sku']] = $new;
        }

        return $skuList;
    }

    function MWSGetFBAFee() {
        try {
            return $this->MWSGetReport(_GET_FBA_ESTIMATED_FBA_FEES_TXT_DATA_, 50);
        } catch (Exception $e) {
            return false;
        }
    }
    
    function addRepriceLogs( $sku,$asin,$price_changed,$our_price,$new_price,$bb_price,$bb ){
        $log = array(
            'id' => null,
            'sellerid' => $this->getSellerId(),
            'sku' => $sku,
            'asin' => $asin,
            'price_changed' => $price_changed,
            'our_price' => $our_price,
            'new_price' => $new_price,
            'bb_price' => $bb_price,
            'bb' => $bb
        );
        $this->log('added logs for '.$sku);
        return $this->db->insert("reprice_logs", $log);
    }

}
