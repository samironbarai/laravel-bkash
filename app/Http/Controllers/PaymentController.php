<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class PaymentController extends Controller
{
    public function token()
    {
        session_start();

        $request_token = $this->_bkash_Get_Token();
        $idtoken = $request_token['id_token'];

        $_SESSION['token'] = $idtoken;

        /*$strJsonFileContents = file_get_contents("config.json");
        $array = json_decode($strJsonFileContents, true);*/

        $array = $this->_get_config_file();

        $array['token'] = $idtoken;

        $newJsonString = json_encode($array);
        File::put(storage_path() . '/app/public/config.json', $newJsonString);

        echo $idtoken;
    }

    protected function _bkash_Get_Token()
    {
        /*$strJsonFileContents = file_get_contents("config.json");
        $array = json_decode($strJsonFileContents, true);*/

        $array = $this->_get_config_file();

        $post_token = array(
            'app_key' => $array["app_key"],
            'app_secret' => $array["app_secret"]
        );

        $url = curl_init($array["tokenURL"]);
        $proxy = $array["proxy"];
        $posttoken = json_encode($post_token);
        $header = array(
            'Content-Type:application/json',
            'password:' . $array["password"],
            'username:' . $array["username"]
        );

        curl_setopt($url, CURLOPT_HTTPHEADER, $header);
        curl_setopt($url, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($url, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($url, CURLOPT_POSTFIELDS, $posttoken);
        curl_setopt($url, CURLOPT_FOLLOWLOCATION, 1);
        //curl_setopt($url, CURLOPT_PROXY, $proxy);
        $resultdata = curl_exec($url);
        curl_close($url);
        return json_decode($resultdata, true);
    }

    protected function _get_config_file()
    {
        $path = storage_path() . "/app/public/config.json";
        return json_decode(file_get_contents($path), true);
    }

    public function createpayment()
    {
        session_start();

        /*$strJsonFileContents = file_get_contents("config.json");
        $array = json_decode($strJsonFileContents, true);*/

        $array = $this->_get_config_file();

        $amount = $_GET['amount'];
        $invoice = $_GET['invoice']; // must be unique
        $intent = "sale";
        $proxy = $array["proxy"];
        $createpaybody = array('amount' => $amount, 'currency' => 'BDT', 'merchantInvoiceNumber' => $invoice, 'intent' => $intent);
        $url = curl_init($array["createURL"]);

        $createpaybodyx = json_encode($createpaybody);

        $header = array(
            'Content-Type:application/json',
            'authorization:' . $array["token"],
            'x-app-key:' . $array["app_key"]
        );

        curl_setopt($url, CURLOPT_HTTPHEADER, $header);
        curl_setopt($url, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($url, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($url, CURLOPT_POSTFIELDS, $createpaybodyx);
        curl_setopt($url, CURLOPT_FOLLOWLOCATION, 1);
        //curl_setopt($url, CURLOPT_PROXY, $proxy);

        $resultdata = curl_exec($url);
        curl_close($url);
        echo $resultdata;
    }

    public function executepayment()
    {
        session_start();

        /*$strJsonFileContents = file_get_contents("config.json");
        $array = json_decode($strJsonFileContents, true);*/

        $array = $this->_get_config_file();

        $paymentID = $_GET['paymentID'];
        $proxy = $array["proxy"];

        $url = curl_init($array["executeURL"] . $paymentID);

        $header = array(
            'Content-Type:application/json',
            'authorization:' . $array["token"],
            'x-app-key:' . $array["app_key"]
        );

        curl_setopt($url, CURLOPT_HTTPHEADER, $header);
        curl_setopt($url, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($url, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($url, CURLOPT_FOLLOWLOCATION, 1);
        // curl_setopt($url, CURLOPT_PROXY, $proxy);

        $resultdatax = curl_exec($url);
        curl_close($url);

        $this->_updateOrderStatus($resultdatax);

        echo $resultdatax;
    }

    protected function _updateOrderStatus($resultdatax)
    {
        $resultdatax = json_decode($resultdatax);

        if ($resultdatax && $resultdatax->paymentID != null && $resultdatax->transactionStatus == 'Completed') {
            DB::table('orders')->where([
                'invoice' => $resultdatax->merchantInvoiceNumber
            ])->update([
                'status' => 'Processing', 'trxID' => $resultdatax->trxID
            ]);
        }
    }
}
