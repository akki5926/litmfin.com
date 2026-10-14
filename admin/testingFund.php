<?php
require_once('auth_guard.php');

	require_once('database.php');
    $db = new MYSQLDB();
    
    $amcCode = $_REQUEST['amctxt'];
    $productCode = $_REQUEST['pdttxt'];

    $result = $db->testFundDB($amcCode,$productCode);

    foreach($result as $row){
        echo "Amc Code = " .$row['amc_code'].'<br>'."product code = " .$row['product_code'].'<br>'."product long = ".$row['product_long_name'].'<br>'."SYSTEMATIC_FREQUENCIES = ".$row['systematic_frequencies'].'<br>'."SIP_DATES = ".$row['sip_dates'].'<br>'."STP_DATES =".$row['stp_dates'].'<br>'."SWP_DATES = ".$row['swp_dates'].'<br>'."PURCHASE_ALLOWED = ".$row['purchase_allowed'].'<br>'."SWITCH_ALLOWED = ".$row['switch_allowed'].'<br>'.
            "REDEMPTION_ALLOWED = ".$row['redemption_allowed'].'<br>'."SIP_ALLOWED = ".$row['stp_allowed'].'<br>'.
            "SWP_ALLOWED = ".$row['swp_allowed'].'<br>'."REINVEST_TAG = ".$row['reinvest_tag'].'<br>'.
            "PRODUCT_CATEGORY = ".$row['product_category'].'<br>'."ISIN = ".$row['isin'].'<br>'.
            "LAST_MODIFIED_DATE = ".$row['last_modified_date'].'<br>'.
            "ACTIVE_FLAG = ".$row['active_flag'].'<br>'."ASSET_CLASS =".$row['asset_class'].'<br>'.
            "SUB_FUND_CODE = ".$row['sub_fund_code'].'<br>'.
            "PLAN_TYPE = ".$row['plan_type'].'<br>'.
            "INSURANCE_ENABLED = ".$row['insurance_enabled'].'<br>'.
            "RTACODE = ".$row['rtacode'].'<br>'.
            "NFO_ENABLED = ".$row['nfo_enabled'].'<br>'.
            "NFO_CLOSE_DATE = ".$row['nfo_close_date'].'<br>'.
            "NFO_SIP_EFFECTIVE_DATE = ".$row['nfo_sip_effective_date'].'<br>'.
            "ELSS = ".$row['elss'].'<br>'.
            "ETF = ".$row['etf'].'<br>'."Arbitrage = ".$row['arbitrage'].'<br>'.
            "Small-cap = ".$row['small_cap'].'<br>'."Mid-cap = ".$row['mid_cap'].'<br>'."Multi-cap = ".$row['multi_cap'].'<br>'.
            "Large-cap = ".$row['large_cap'].'<br>'.
            "focussed = ".$row['focussed'].'<br>'."Sector Funds = ".$row['sector_funds'].'<br>'.
            "Other Equities = ".$row['other_equities'].'<br>'.
            "Equity = ".$row['equity'].'<br>'.
            "Hybrid = ".$row['hybrid'].'<br>'."Debt = ".$row['debt'].'<br>'.
            "Other Debt = ".$row['other_debt'].'<br>'."Bond = ".$row['bond'].'<br>'."Credit Risk = ".$row['credit_risk'].'<br>'.
            "STP = ".$row['stp'].'<br>'."Gilt = ".$row['gilt'].'<br>'."Cash/Liquid = ".$row['cash_liquid'].'<br>'.
            "FOF = ".$row['fof'].'<br>'."Gold = ".$row['gold'];
    }   
	
?>
