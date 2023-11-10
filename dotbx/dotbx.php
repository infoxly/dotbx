<?php
if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}
use WHMCS\Module\Registrar\dotbx\ApiReseller;
use WHMCS\Database\Capsule;
use WHMCS\Domain\TopLevel\ImportItem;
use WHMCS\Domains\DomainLookup\ResultsList;
use WHMCS\Domains\DomainLookup\SearchResult;

function dotbx_MetaData() {
    return array(
        'DisplayName' => '#1 DotBX',
        'APIVersion' => '1.0',
    );
}

function dotbx_GetConfigArray()
{
    return array(
        "Description"   => array("Type" => "System","Value" => "DotBX: The Industry Leader in Offering the Lowest-Priced Domains Across the Market."),
        "auth-userid"   => array("FriendlyName" => "ResellerID:","Type" => "text","Size" => "25","Default" => "", "Description" => "Enter your ResellerID."),
        "api-key"       => array("FriendlyName" => "API Key:","Type" => "text","Size" => "25","Default" => "", "Description" => "Enter your DotBX API Key here.")
        );
}

function dotbx_CheckAvailability($params){

    $TLDs = $params["tldsToInclude"];    
    $domains = array();
    foreach ($TLDs as $TLD) {
        $domains[] = $params['sld'] . $TLD;
    }
    
    $postfields["domains"] = ApiReseller::implodeToString($domains);
    $postfields["premiumEnabled"] =$params["premiumEnabled"];
    
    $response = ApiReseller::call("domains/checkAvailability", "POST", $postfields );
    
    if(!$response["success"]){
     
     throw new Exception(json_encode($response["errors"][0]["message"]));
    }
    else
    {
        
        try
        {
            $results = new ResultsList();
            
            foreach( $response["domains"] as $domainName => $domainData ){
                
                 // Instantiate a new domain search result object
                 
                 $searchResult = new SearchResult($domainData["sld"], $domainData["tld"]);
                 
                 if($domainData["available"] == TRUE){
                     $status = SearchResult::STATUS_NOT_REGISTERED;
                 }else
                 {
                     $status = SearchResult::STATUS_REGISTERED;
                 }
            }
            
            $searchResult->setStatus($status);
            $results->append($searchResult);
            
            return $results;
        } catch (\Exception $e) {
            return array(
                'error' => $e->getMessage(),
            );
        }  
    }
}

function dotbx_RegisterDomain($params){
    
    $res = ApiReseller::GetCustomerID($params);
    
    if($res['success']){
        
        $customerid = $res['customerid'];
         // submitted nameserver values
        $nameserver1 = $params['ns1'];
        $nameserver2 = $params['ns2'];
        $nameserver3 = $params['ns3'];
        $nameserver4 = $params['ns4'];
        $nameserver5 = $params['ns5'];

        if ($nameserver1) {
            $nameServer[] = strtolower( $params['ns1'] );
        }
        if ($nameserver2) {
            $nameServer[] = strtolower( $params['ns2'] );
        }
        if ($nameserver3) {
            $nameServer[] = strtolower( $params['ns3'] );
        }
        if ($nameserver4) {
            $nameServer[] = strtolower( $params['ns4'] );
        }
        if ($nameserver5) {
            $nameServer[] = strtolower( $params['ns5'] );
        } 
        $contectType    =   dotbx_ContactType($params);//"Contact";
        $contect = ApiReseller::searchcontact($params, $customerid, $contectType);
        
        if($contect["success"])
        {   
            
            $postfields['customerid']       =   $customerid;
            $postfields['domainname']       =   $params['domainname'];
            $postfields['years']            =   $params['regperiod'];
            $postfields['privacy']          =   (bool) $params['idprotection'];   
            $postfields["nameServer"]       =   ApiReseller::implodeToString($nameServer);
            
            $postfields["registrant_handle"]=   $contect["contactid"];
            $postfields["admin_handle"]     =   $contect["contactid"];
            $postfields["tech_handle"]      =   $contect["contactid"];
            $postfields["billing_handle"]   =   $contect["contactid"];
            
            $response = ApiReseller::call('domains/register', 'POST', $postfields );
            
            if(!$response['success']){
                 return  array('error' =>  ApiReseller::error($response['errors']));
            }
            
            // if(!$response['success']){
            //      return  array('error' =>  json_encode($response['errors']));
            // }
        }else{
            return  array('error' =>  ApiReseller::error($contect['errors']));
        }       
    
    }else{
        return  array('error' =>  ApiReseller::error($res['errors']));
    }
}

function dotbx_TransferDomain($params) {
    
    if(!empty($params['transfersecret'])) {
      
     $res = ApiReseller::GetCustomerID($params);
    
    if($res['success']) {
        
        $customerid = $res['customerid'];
        $contectType    =   dotbx_ContactType($params);
        $contect = ApiReseller::searchcontact($params, $customerid, $contectType);
        
        if($contect["success"])
        {
            $postfields['customerid']       =   $customerid;
            $postfields['domainname']       =   $params['domainname'];
            $postfields['password']         =   $params['transfersecret'];
            $postfields['privacy']          =   (bool) $params['idprotection'];   
            
            $postfields["registrant_handle"]=   $contect["contactid"];
            $postfields["admin_handle"]     =   $contect["contactid"];
            $postfields["tech_handle"]      =   $contect["contactid"];
            $postfields["billing_handle"]   =   $contect["contactid"];
        
            $response = ApiReseller::call('domains/transfer', 'POST', $postfields );
            
            // return  array('error' =>  json_encode($response));
            if(!$response['success']){
                 return  array('error' =>  ApiReseller::error($response['errors']));
            }
        }else{
            return  array('error' =>  ApiReseller::error($contect['errors']));
        }        
       
    }else{
        return  array('error' =>  ApiReseller::error($res['errors']));
    }
    }else{
        return  array('error' =>  'EPP Code Can\'t Blank');
    }
}

function dotbx_RenewDomain($params)
{
    $domain = ApiReseller::getDomain($params['domainid']);
    
        if($domain->status == 'Active'){
            
          $order =  ApiReseller::Getorderid($params);
           
          if($order['success']){
              
            $res = ApiReseller::call('domains/details', 'POST', array('orderid' => $order["orderid"] ));

            if($res["success"]){

                $postfields['orderid']  = $order["orderid"];
                $postfields["years"]    = $params["regperiod"];
                $postfields["currentexpiryDate"]= $res["expiryDate"];
                $res = ApiReseller::call('domains/renew', 'POST', $postfields);
                
                if(!$res['success'])
                {
                  if(isset($res['errors'])){
                      return  array('error' =>  ApiReseller::error($res['errors']));
                  }
                  
                }
            }else{
                return  array('error' =>  ApiReseller::error($order['errors']));
            }

          }else{
              return  array('error' =>  ApiReseller::error($order['errors']));
          }
    }else{
            // domain Not Active Whmcs Softwere
        return array('error' => 'Domain is not Active.');

    }
}
function dotbx_GetNameservers($params)
{
   $order =  ApiReseller::Getorderid($params);
   
  if($order['success']){
       
      $orderid =   $order['orderid'];
       
      $res = ApiReseller::call('domains/details', 'POST', array('orderid' => $orderid ));
      if($res['success'])
      {
           
          if (is_array($res['nameServer']) || is_object($res['nameServer']))
          {
               
              $nameservers = array();
               
                  $x=0;
                    foreach($res['nameServer'] as $key){
                        $x++;
                        $ns="ns".$x;
                        $nameservers["ns".$x]   = $key;
                    }
                    
                return $nameservers;
          }else{
               
               
              $error['errors'][] = array('code' => '', 'message' =>  'currently not set nameservers.');
            //   $error['errors'][] = array('code' => '', 'message' =>  'if you placed domain Transfer Request please wait display nameserver after complete Transfer.');
               
                return  array( 'error' =>  ApiReseller::error($error['errors']) );
          }
           
           
      }else{
          return  array('error' =>  ApiReseller::error($order['errors']));
      }
       
  }else{
      return  array('error' =>  ApiReseller::error($order['errors']));
  }
}

function dotbx_SaveNameservers($params)
{
    $domain = ApiReseller::getDomain($params['domainid']);
    
        if($domain->status == 'Active'){
            
          $order =  ApiReseller::Getorderid($params);
           
          if($order['success']){
              
              
               
                // submitted nameserver values
                $nameserver1 = strtolower( $params['ns1'] );
                $nameserver2 = strtolower( $params['ns2'] );
                $nameserver3 = strtolower( $params['ns3'] );
                $nameserver4 = strtolower( $params['ns4'] );
                $nameserver5 = strtolower( $params['ns5'] );
                
                    if ($nameserver1) {
                        $nameServer[] = $nameserver1;
                    }
                    if ($nameserver2) {
                        $nameServer[] = $nameserver2;
                    }
                    if ($nameserver3) {
                        $nameServer[] = $nameserver3;
                    }
                    if ($nameserver4) {
                        $nameServer[] = $nameserver4;
                    }
                    if ($nameserver5) {
                        $nameServer[] = $nameserver5;
                    }
                $postfields['orderid'] = $order["orderid"];
                $postfields['nameServer']= ApiReseller::implodeToString($nameServer);
               
               $res = ApiReseller::call('domains/details', 'PUT', $postfields);
               
              if(!$res['success'])
              {
                  if(isset($res['errors'])){
                      return  array('error' =>  ApiReseller::error($res['errors']));
                  }else{
                      return  array('error' =>  'Nameserver update Failed.'. json_encode($res) );
                  }
                  
              }
          }else{
              return  array('error' =>  ApiReseller::error($order['errors']));
          }
    }else{
            // domain Not Active Whmcs Softwere
        return array('error' => 'Domain is not Active.');

    }
}

function dotbx_GetEPPCode($params)
{
  $order =  ApiReseller::Getorderid($params);
   
  if($order['success']){
       
      $orderid =   $order['orderid'];
       
      $res = ApiReseller::call('domains/details', 'POST', array('orderid' => $orderid ));
       
      if($res['success'])
      {
           
          return array('eppcode' => $res['password']);
           
           
      }else{
          return  array('error' =>  ApiReseller::error($order['errors']));
      }
       
  }else{
      return  array('error' =>  ApiReseller::error($order['errors']));
  }
}

function dotbx_GetRegistrarLock($params)
{
  $order =  ApiReseller::Getorderid($params);
   
  if($order['success']){
       
      $orderid =   $order['orderid'];
       
      $res = ApiReseller::call('domains/details', 'POST', array('orderid' => $orderid ));
       
      if($res['success'])
      {
           
          if($res['transferlock']){
              return 'locked';
          }else{
              return 'unlocked';
          }
           
           
      }else{
          return  array('error' =>  ApiReseller::error($order['errors']));
      }
       
  }else{
      return  array('error' =>  ApiReseller::error($order['errors']));
  }
}


function dotbx_SaveRegistrarLock($params){
    
    $domain = ApiReseller::getDomain($params['domainid']);
    
    if($domain->status == 'Active'){
        
        $order =  ApiReseller::Getorderid($params);
        if($order['success']){
            
            $postfields['orderid'] = $order["orderid"];
            $postfields['transferlock'] =  false;
            
            if($params['lockenabled'] == 'locked'){
                
                $postfields['transferlock'] =  true;
            }
            
            $res = ApiReseller::call('domains/details', 'PUT', $postfields);
            if(!$res['success']){
                return  array('error' =>  ApiReseller::error($res['errors']));
            }
        }else{
            return  array('error' =>  ApiReseller::error($order['errors']));
        }
    }else{
            // domain Not Active Whmcs Softwere
        return array('error' => 'Domain is not Active.');

    }
}

function dotbx_RegisterNameserver($params){
    
    $domain = ApiReseller::getDomain($params['domainid']);
    
    if($domain->status == 'Active'){
        
        $order =  ApiReseller::Getorderid($params);
        if($order['success']){
            
            $postfields["orderid"]      =   $order['orderid'];
            $postfields["hostname"]     =   $params["nameserver"];
            $postfields["ip"]           =   $params["ipaddress"];
            
            
            $res = ApiReseller::call('domains/details', 'PATCH', $postfields);
            if(!$res['success']){
                return  array('error' =>  ApiReseller::error($res['errors']));
            }else{
                return array( "success" => "success");
            }
            
        }else{
            return  array('error' =>  ApiReseller::error($order['errors']));
        }
    }else{
        return array('error' => 'Domain is not Active.');
    }
}
function dotbx_ModifyNameserver($params){
    
    $domain = ApiReseller::getDomain($params['domainid']);
    
    if($domain->status == 'Active'){
        
        $order =  ApiReseller::Getorderid($params);
        if($order['success']){
            
            $postfields["orderid"]      =   $order['orderid'];
            $postfields["hostname"]     =   $params["nameserver"];
            $postfields["ip"]           =   $params["newipaddress"];
            
            $res = ApiReseller::call('domains/details', 'PUT', $postfields);
            if(!$res['success']){
                return  array('error' =>  ApiReseller::error($res['errors']));
            }else{
                return array( "success" => "success");
            }
            
        }else{
            return  array('error' =>  ApiReseller::error($order['errors']));
        }
    }else{
        return array('error' => 'Domain is not Active.');
    }
}
function dotbx_DeleteNameserver($params){
    
    $domain = ApiReseller::getDomain($params['domainid']);
    
    if($domain->status == 'Active'){
        
        $order =  ApiReseller::Getorderid($params);
        if($order['success']){
            
            $postfields["orderid"]      =   $order['orderid'];
            $postfields["hostname"]     =   $params["nameserver"];
            
            $res = ApiReseller::call('domains/details', 'DELETE', $postfields);
            if(!$res['success']){
                return  array('error' =>  ApiReseller::error($res['errors']));
            }else{
                return array( "success" => "success");
            }
            
        }else{
            return  array('error' =>  ApiReseller::error($order['errors']));
        }
    }else{
        return array('error' => 'Domain is not Active.');
    }
}


function dotbx_Sync($params){
  $order =  ApiReseller::Getorderid($params);
   
  if($order['success']){
       
      $orderid =   $order['orderid'];
       
      $res = ApiReseller::call('domains/details', 'POST', array('orderid' => $orderid ));
       
      if($res['success'])
      {
           if($res["currentstatus"] == "Active"){
               return array("active" => true, "expired" => false, "expirydate" => date('Y-m-d', strtotime( $res["expiryDate"] ) ) );
           }
      }else{
          return  array('error' =>  ApiReseller::error($order['errors']));
      }
  }else{
      return  array('error' =>  ApiReseller::error($order['errors']));
  }
}
function dotbx_TransferSync($params){
  $order =  ApiReseller::Getorderid($params);
   
  if($order['success']){
       
      $orderid =   $order['orderid'];
       
      $res = ApiReseller::call('domains/details', 'POST', array('orderid' => $orderid ));
       
      if($res['success'])
      {     
          $currentstatus = $res["currentstatus"];
          
          if($currentstatus == "Inactive"){
              return array("inprogress" => true);
          }else
          if($currentstatus == "Active"){
              return array("completed" => true, "failed" => false, "expirydate" => date('Y-m-d', strtotime( $res["expiryDate"] ) ) );
          }else{
              return array("failed" => true, "reason" => "contect Support" );
          }
      }else{
          return  array('error' =>  ApiReseller::error($order['errors']));
      }
  }else{
      return  array('error' =>  ApiReseller::error($order['errors']));
  }
}

function dotbx_GetTldPricing(array $params){
    
    $postfields = array();
    $response = ApiReseller::call('products/reseller-cost-price', 'POST', $postfields);
    
    if($response['success']) {
        
        if($response["page_info"]["total_count"] >=1){
            $results = new ResultsList();
            try{
                
                foreach ($response['result'] as $extension) {
                    // All the set methods can be chained and utilised together.
    
                    $item = (new ImportItem)
                        ->setExtension($extension['zone'])
                        ->setMinYears('1')
                        ->setMaxYears('10')
                        ->setRegisterPrice($extension['AddNewDomain'])
                        ->setRenewPrice($extension['RenewDomain'])
                        ->setTransferPrice($extension['AddTransferDomain'])
                        ->setRedemptionFeeDays($extension['redemptionperiod'])
                        ->setRedemptionFeePrice($extension['RestoreDomain'])
                        ->setEppRequired(TRUE)
                        ->setCurrency($extension['currencycode']);
    
                     $results[] = $item;                
                }
              return $results;
            } catch (\Exception $e) {
                return array( 'error' => $e->getMessage() );
            }
        }else{
            return array( 'error' => "data unavailable." );
        }
    }else{
         return array( 'error' => $response['errors'][0]['message'] );
    }
}
function dotbx_sync_expiry_date($params)
{
    $domain = ApiReseller::getDomain($params['domainid']);
    
        if($domain->status == 'Active'){
            
          $order =  ApiReseller::Getorderid($params);
           
          if($order['success']){
              
              $orderid =   $order['orderid'];
               
              $res = ApiReseller::call('domains/details', 'POST', array('orderid' => $orderid ));
               
              if($res['success'])
              {
                  //update domain expiry table data
                  Capsule::table('tbldomains')
                        ->where('id', '=', $params['domainid'])
                        ->where('userid', '=', $params['userid'])
                        ->update([ 'expirydate'   =>   $res['expiryDate'] ]);
                        
                  header("Refresh:0; url=clientsdomains.php?userid={$params['userid']}&domainid=".$params['domainid']);
                   
              }else{
                  return  array('error' =>  ApiReseller::error($order['errors']));
              }

              
          }else{
              return  array('error' =>  ApiReseller::error($order['errors']));
          }
    }else{
            // domain Not Active Whmcs Softwere
        return array('error' => 'Domain is not Active.');

    }
}

function dotbx_ContactType($params)
{
    $params = injectDomainObjectIfNecessary($params);
    if ($params["domainObj"]->getLastTLDSegment() == "uk") {
        $contacttype = "UkContact";
    } else {
        if ($params["domainObj"]->getLastTLDSegment() == "eu") {
            $contacttype = "EuContact";
        } else {
            if ($params["domainObj"]->getLastTLDSegment() == "cn") {
                $contacttype = "CnContact";
            } else {
                if ($params["domainObj"]->getLastTLDSegment() == "co") {
                    $contacttype = "CoContact";
                } else {
                    if ($params["domainObj"]->getLastTLDSegment() == "ca") {
                        $contacttype = "CaContact";
                    } else {
                        if ($params["domainObj"]->getLastTLDSegment() == "es") {
                            $contacttype = "EsContact";
                        } else {
                            if ($params["domainObj"]->getLastTLDSegment() == "de") {
                                $contacttype = "DeContact";
                            } else {
                                if ($params["domainObj"]->getLastTLDSegment() == "ru") {
                                    $contacttype = "RuContact";
                                } else {
                                    if ($params["domainObj"]->getLastTLDSegment() == "nl") {
                                        $contacttype = "NlContact";
                                    } else {
                                        if ($params["domainObj"]->getLastTLDSegment() == "mx") {
                                            $contacttype = "MxContact";
                                        } else {
                                            if ($params["domainObj"]->getLastTLDSegment() == "br") {
                                                $contacttype = "BrContact";
                                            } else {
                                                if ($params["domainObj"]->getLastTLDSegment() == "nyc") {
                                                    $contacttype = "NycContact";
                                                } else {
                                                    if ($params["domainObj"]->getLastTLDSegment() == "tel") {
                                                        $contacttype = "Contact";
                                                    } else {
                                                        $contacttype = "Contact";
                                                    }
                                                }
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
    }
    return $contacttype;
}

function rdotbx_DomainSuggestionOptions() {
    return array(
        'includeCCTlds' => array(
            'FriendlyName' => 'Include Country Level TLDs',
            'Type' => 'yesno',
            'Description' => 'Tick to enable',
        ),
    );
}
function dotbx_AdminCustomButtonArray() {
    $buttonarray = array(
	 "Sync expiry date" => "sync_expiry_date",
	);
	return $buttonarray;
}
