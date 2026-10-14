<?php
require_once('auth_guard.php');
 

	require_once('database.php');
	$db = new MYSQLDB();

	$Id = $_REQUEST['id'];
    $res =  $db->getRecData($Id);

    foreach ($res as $row) {
    	
    	$invest_type = $row["invest_type"];	# code...
    }
    

    if ($invest_type == "equity") {
    	echo '
    	<option value = 0>Select Invest Type</option>
    	<option value = "equity" selected>Equity</option>
    	<option value="gold">Gold</option>
			<option value="debt">Debt</option>
			<option value="fof">FOF</option>
			<option value="hybrid">Hybrid</option>
			<option value="taxsaving">Tax Saving</option>
			<option value="arbitrage">Arbitrage</option>
			<option value="etf">ETF</option>';
    	# code...
    }else if($invest_type == "gold"){

    	echo '
    	<option value = 0>Select Invest Type</option>
    	<option value = "equity" >Equity</option>
    	<option value="gold" selected>Gold</option>
			<option value="debt">Debt</option>
			<option value="fof">FOF</option>
			<option value="hybrid">Hybrid</option>
			<option value="taxsaving">Tax Saving</option>
			<option value="arbitrage">Arbitrage</option>
			<option value="etf">ETF</option>';

    	
    }else if($invest_type == "debt"){

    	echo '
    	<option value = 0>Select Invest Type</option>
    	<option value = "equity" >Equity</option>
    	<option value="gold" >Gold</option>
			<option value="debt" selected>Debt</option>
			<option value="fof">FOF</option>
			<option value="hybrid">Hybrid</option>
			<option value="taxsaving">Tax Saving</option>
			<option value="arbitrage">Arbitrage</option>
			<option value="etf">ETF</option>';

    	
    }
    else if($invest_type == "fof"){

    	echo '
    	<option value = 0>Select Invest Type</option>
    	<option value = "equity" >Equity</option>
    	<option value="gold" >Gold</option>
			<option value="debt" >Debt</option>
			<option value="fof" selected>FOF</option>
			<option value="hybrid">Hybrid</option>
			<option value="taxsaving">Tax Saving</option>
			<option value="arbitrage">Arbitrage</option>
			<option value="etf">ETF</option>';

    	
    }
    else if($invest_type == "hybrid"){

    	echo '
    	<option value = 0>Select Invest Type</option>
    	<option value = "equity" >Equity</option>
    	<option value="gold" >Gold</option>
			<option value="debt" >Debt</option>
			<option value="fof" >FOF</option>
			<option value="hybrid" selected>Hybrid</option>
			<option value="taxsaving">Tax Saving</option>
			<option value="arbitrage">Arbitrage</option>
			<option value="etf">ETF</option>';

    	
    }

    else if($invest_type == "taxsaving"){

    	echo '
    	<option value = 0>Select Invest Type</option>
    	<option value = "equity" >Equity</option>
    	<option value="gold" >Gold</option>
			<option value="debt" >Debt</option>
			<option value="fof" >FOF</option>
			<option value="hybrid" >Hybrid</option>
			<option value="taxsaving" selected>Tax Saving</option>
			<option value="arbitrage">Arbitrage</option>
			<option value="etf">ETF</option>';

    	
    }
    else if($invest_type == "arbitrage"){

    	echo '
    	<option value = 0>Select Invest Type</option>
    	<option value = "equity" >Equity</option>
    	<option value="gold" >Gold</option>
			<option value="debt" >Debt</option>
			<option value="fof" >FOF</option>
			<option value="hybrid" >Hybrid</option>
			<option value="taxsaving" >Tax Saving</option>
			<option value="arbitrage" selected>Arbitrage</option>
			<option value="etf">ETF</option>';

    	
    }

    else if($invest_type == "etf"){

    	echo '
    	<option value = 0>Select Invest Type</option>
    	<option value = "equity" >Equity</option>
    	<option value="gold" >Gold</option>
			<option value="debt" >Debt</option>
			<option value="fof" >FOF</option>
			<option value="hybrid" >Hybrid</option>
			<option value="taxsaving" >Tax Saving</option>
			<option value="arbitrage" >Arbitrage</option>
			<option value="etf" selected>ETF</option>';

    	
    }else{
    	echo "Something went wrong";
    }
		


		
		//header("location:index.php");	
	
?>

