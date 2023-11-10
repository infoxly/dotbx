<?php
use WHMCS\Module\AbstractWidget;
use WHMCS\Module\Registrar\dotbx\ApiReseller;


add_hook('AdminHomeWidgets', 1, function() {
    return new dotbxWidget();
});


class dotbxWidget extends \WHMCS\Module\AbstractWidget
{
    protected $title = "DotBX Balance";
    
     public function getData(){
        
        $postfields=array();
        $response = ApiReseller::call("resellers/getaccountbalance", "POST", $postfields );
        
        if($response["success"] === TRUE){
            
            $dataArray = array(
                "success"       =>  TRUE,
                "currencycode"  =>  $response["currencycode"],
                "currencysymbol"  =>  $response["currencysymbol"],
                "availableFund" =>  $response["availableFund"]
            );
        }else
        {
            $dataArray["error"] = ApiReseller::error($response['errors']);
        }
        
        
        return $dataArray;
     }
    public function generateOutput($data){
        
        if($data["success"] === TRUE){
 
        return <<<EOF
            <div class="widget-content-padded">
                <div class="row text-center">
                    <div class="col-sm-12">
                        <h4><strong>{$data["currencysymbol"]} {$data["availableFund"]} {$data["currencycode"]}</strong></h4>
                        Balance
                    </div>
                </div>
            </div>
EOF;
        }
else
{
        return <<<EOF
    <div class="widget-content-padded">
        <strong>There was an error:</strong><br/>
        {$data["error"]}
    </div>
EOF;

}
    }
}