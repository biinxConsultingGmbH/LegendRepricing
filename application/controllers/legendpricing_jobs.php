<?php

/**
 * Description of legendpricing_jobs
 *
 * @author 
 */
class legendpricing_jobs extends CI_Controller {

    private $client;
    public $db;

    public function __construct() {
        parent::__construct();
        if (php_sapi_name() !== 'cli') {
            //show_404();
        }
        $CI = & get_instance();
        $this->db = $CI->db;
        $this->load->helper('mws_common');
        $this->load->helper('mws_seller');
        $this->client = new LPGM_Client();
    }

    public function test() {
        $seller = new MWS_Seller('A1ERLGARDFTEUE');
        $seller->addRepriceLogs('sku123','asin123','yes', 100, 101, 102,'no');
        exit();
        //$seller = new MWS_Seller('A3NP85KUD0UI05');
        $this->load->helper('mws_reprice');
//
//        $data = $seller->MWSGetReport(_GET_MERCHANT_LISTINGS_DATA_,1);
//        $data = $seller->MWSProductListingData(array('QHDC gray','LH-F8UN-9CLQ'));
//        debug($data);
//        exit();
        while (true) {
            $data = $seller->MWSProductListingData(array('QHDC gray','LH-F8UN-9CLQ'));
            foreach ($data as $sku => $p) {
                $rules = $seller->LocalGetProduct($sku);
                $lp = new LegendRepricer($p, $rules);
                $lp->reprice();
                $bb = $lp->hasBuyBox ? '*' : ' ';
                cli_echo("### {$lp->ourPrice->landed}{$bb} = $sku");
            }
//            sleep(2);
        }

        exit();
    }
    function get_feed_status(){
        $seller = new MWS_Seller('A1ERLGARDFTEUE');
        $feeds = $seller->MWSGetFeed($_GET['fid']);
        debug($feeds);
    }
    function sync_inventory() {
        $query = 'SELECT sellerid FROM user_settings';
        $sellers = $this->db->query($query)->result_array();
        foreach ($sellers as $row) {
            $sellerId = $row['sellerid'];
            $data['update_fbafees'] = true;
            $data['sellerid'] = $sellerId;
            $this->client->addTask(new LPGM_Task('syncListings', $data));
        }
    }

    public function sync_listings($sellerId) {
        $query = "UPDATE user_listings SET last_repriced=null WHERE sellerid='{$sellerId}'";
        $this->db->query($query);
        $data['update_fbafees'] = false;
        $data['sellerid'] = $sellerId; //'ANF2DSU3YZFVJ';
        $this->client->addTask(new LPGM_Task('syncListings', $data));
    }

    function reprice_products() {
        $query = "SELECT sellerid from user_settings";
        $sellers = $this->db->query($query)->result_array();
        $sellersToReprice = array();
        foreach($sellers as $row){
            $seller = new MWS_Seller($row['sellerid']);
            if( $seller->isAuthrized() ){
                $sellersToReprice[] = "'".$row['sellerid']."'";
            }
        }
        if( empty($sellersToReprice) ){
            die('No seller to reprice');
        }
        
        $sellersToReprice = implode(',',$sellersToReprice);
        $query = "SELECT sku,sellerid, TIMESTAMPDIFF(MINUTE, last_repriced, now()) minutes FROM user_listings WHERE
                    status='active' AND ( TIMESTAMPDIFF(MINUTE, last_repriced, now()) >= 15 OR last_repriced IS NULL) 
                    AND sellerid IN ({$sellersToReprice}) ";
        $skuArray = $this->db->query($query)->result_array();
        $start = 0;
        $limit = 500;
        while ($skuList = array_slice($skuArray, $start, $limit, true)) {
            $task = new LPGM_Task('reprice_products', $skuList);
            $this->client->addTask($task);
            $start += $limit;
        }
    }

    public function reprice_products_old() {
//        $conditions = array('sellerid' => 'ANF2DSU3YZFVJ');
//        $conditions = array();
        $results = $this->db->get_where("user_settings", $conditions, 4)->result();
//        debug($results);exit();
        foreach ($results as $row) {
            $task = new LPGM_Task('reprice_products', $row->sellerid);
            $this->client->addTask($task);
            //break;
        }
    }

    function sqs() {
        $results = $this->db->get_where("pollingtbl")->result_array();
        $task = new LPGM_Task('mws_sqs', $results);
        $this->client->addTask($task);
    }

}
