<?php
namespace WHMCS\Module\Registrar\dotbx;
use WHMCS\Database\Capsule;

class ApiReseller{
    
     const API_URL = 'https://api.dotbx.com/v1/shop/';
    
    public static function call($action, $method, $postfields){
        
        $api_key    =  decrypt(ApiReseller::getauth('api-key')->value, $cc_encryption_hash);
        $auth_userid =  decrypt(ApiReseller::getauth('auth-userid')->value, $cc_encryption_hash);
        
        
        $return['success'] = FALSE;
        
        $session = curl_init();
        curl_setopt($session, CURLOPT_URL, self::API_URL . $action . '.php');
        curl_setopt($session, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($session, CURLOPT_POSTFIELDS, json_encode($postfields));
        curl_setopt($session, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($session, CURLOPT_SSL_VERIFYHOST, 2);
        curl_setopt($session, CURLOPT_SSL_VERIFYPEER, 1);
        curl_setopt($session, CURLOPT_TIMEOUT, 100);
        curl_setopt($session, CURLOPT_HTTPHEADER, array(
            
            'api-key: '. $api_key .'',
            'auth-userid: '. $auth_userid .''
            
            )); 
            
        $response = curl_exec($session);
        
        if (curl_errno($session)) {
            $ip = dotbx_GetIP();
            $ip2 = isset($_SERVER["SERVER_ADDR"]) ? $_SERVER["SERVER_ADDR"] : $_SERVER["LOCAL_ADDR"];
            
            $return['errors'][] =   array('message' => "CURL Error: " . curl_errno($session) . " - " . curl_error($session) . " (IP: " . $ip . " & " . $ip2 . ")" );
            // $return['errors'][] =   array('message' => 'curl error: ' . curl_errno($session) . " - " . curl_error($session));
        }    
        
        curl_close($session);
        
        $results = json_decode($response, true);
        
        if ($results === null && json_last_error() !== JSON_ERROR_NONE) {
            $return['errors'][] =   array('message' => 'No responce received from api');
        }else{
            $return = $results;
        }
        
        return $return;            
            
    }    
    
    public static function Getorderid($params){
        
        $return['success'] = FALSE;
        
        $res = ApiReseller::call('domains/orderid', 'POST', array('domainname' =>   $params['domainname'] ) );
        
        if($res['success']){
            
            $return['success'] = TRUE;
            $return['orderid'] = $res['orderid'];
            
        }else{
            
            $return = $res;
        }
        
        return $return;
    }
    
    public static function searchcontact(array $params, $customerid, $contectType ){
        
        $return['success'] = FALSE;
        
        $postfields["customerid"]   =   $customerid;
        $postfields["contectType"]  =   $contectType;
        $postfields["useremail"]    =   trim( $params["email"] );
        
        $res = ApiReseller::call('contacts/search', 'POST', $postfields );
        
        if($res["success"]){
            
            if($res["page_info"]["count"]>0){
                
                $return['success'] = TRUE;
                $return["contactid"] = $res["result"][0]["id"];
                $return["useremail"] = $res["result"][0]["useremail"];
            }else{
                $return = ApiReseller::Add_contacts($params, $customerid, $contectType);
            }
        }else{
            $return =$postfields;
        }
        return $return;
    }
    public static function Add_contacts(array $params, $customerid, $contectType){
        $return['success'] = FALSE;
        
         $fullstate = trim( $params["fullstate"] );
        if($fullstate == "Chhattīsgarh"){
            $fullstate = "Chhattisgarh";
        }
        $postfields["customerid"]   =   $customerid;
        $postfields["contectType"]  =   $contectType;
        $postfields["firstname"]    =   trim( $params["firstname"] );
        $postfields["lastname"]     =   trim( $params["lastname"] );
        $postfields["companyname"]  =   isset($params['companyname']) && (!empty($params['companyname'])) && is_string($params['companyname']) ? $params['companyname'] : "N/A";
        $postfields["useremail"]    =   trim( $params["email"] );
        $postfields["phone"]        =   trim( $params["fullphonenumber"] );
        $postfields["address"]      =   trim( $params["address1"] ." ". $params["address2"] );
        $postfields["city"]         =   trim( $params["city"] );
        $postfields["statename"]    =   $fullstate;
        $postfields["postalCode"]   =   trim( $params["postcode"] );
        $postfields['countrycode']  =   $params['countrycode'];             
        // $postfields["countryname"]  =   trim( $params["countryname"] );
        
        $res = ApiReseller::call('contacts/Add', 'POST', $postfields );
        
        if($res["success"]){
            
            $return['success'] = TRUE;
            $return["contactid"]    =   $res["contactid"];
            $return["useremail"]    =   trim( $params["email"] );
            
        }else{
            
            $return = $res;
        }
        return $return;
    }
    
    public static function getauth($setting) {
        return Capsule::table('tblregistrars')
                        ->where('registrar', '=', 'dotbx')
                        ->where('setting', '=', $setting)
                        ->select('value')
                        ->first();
    } 
    
    public static function getDomain($domainid) {
        return Capsule::table('tbldomains')
                        ->where('id', '=', $domainid)
                        ->first();
    }
    
    public static function GetCustomerID($params){
        
        $return['success'] = FALSE;
        $res = ApiReseller::call('customer/details', 'POST', array('useremail' => $params['email']));
        
            if($res['success']){
                
                $return['success'] = TRUE;
                $return['customerid'] = $res['customerid'];
            }else{
             $return = ApiReseller::AddCustomer($params);
            }
        
        return $return;
        
    }
    
    public static function AddCustomer($params){
        
         $fullstate = trim( $params["fullstate"] );
        if($fullstate == "Chhattīsgarh"){
            $fullstate = "Chhattisgarh";
        }
        
        $postfields['firstname']    =   trim($params['firstname']);               //    Required
        $postfields['lastname']     =   trim($params['lastname']);                //    Required
        $postfields["companyname"]  =   isset($params['companyname']) && (!empty($params['companyname'])) && is_string($params['companyname']) ? $params['companyname'] : "N/A";             //    Required
        $postfields['useremail']    =   $params['email'];                   //    Required
        $postfields['address']      =   trim($params['address1'] . ' '.$params['address2']);     //    Required
        $postfields['city']         =   trim($params['city']);                     //    Required
        $postfields['statename']    =   trim($params['state']);                    //    Required
        $postfields['postalCode']   =   trim($params['postcode']);                //    Required
        $postfields['countrycode']  =   $params['countrycode'];             // Not Required
        // $postfields['countryname']  =   trim($params['countryname']);             //    Required
        $postfields['phone']        =   $params['fullphonenumber'];
        
        $postfields['whatsapp']     =   $params['fullphonenumber'];             //optinal
        $postfields['statename']    =   $fullstate;               // Not Required
        $postfields['statecode']    =   $params['statecode'];               // Not Required
        $postfields['phonecc']      =   $params['phonecc'];                 // Not Required
        $postfields['phonenumber']  =   $params['phonenumber'];             // Not Required
        
        return ApiReseller::call('customer/create', 'POST', $postfields);        
    }
    
    public static function explodeToArray($data) {
        
        $data = str_replace(' ', '', trim($data));
        
        $return = NULL;
        
        if(($data != NULL ) || ($data != '' )){ $return = explode(",",$data); }
        
        return $return;
    }
    public static function implodeToString($data) {
        
        $return = NULL;
        
        if(($data != NULL ) || ($data != '' )){ $return = implode(",",$data); }
        
        return $return;
    }    
    public static function error($error){
        $x;
        $return = array();
        foreach ($error as $key => $value){
            
            $return[] = $value['message'];
          $x++;  
        }
        return $x. " Message ==> ( " . implode(", ", $return) ." )";
    }
    
    function dotbx_GetIP()
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "https://api1.whmcs.com/ip/get");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        $contents = curl_exec($ch);
        curl_close($ch);
        if (!empty($contents)) {
            $data = json_decode($contents, true);
            if (is_array($data) && isset($data["ip"])) {
                echo $data["ip"];
            }
        }
        return "";
    }
}
