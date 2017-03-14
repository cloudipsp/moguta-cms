<?php
class Pactioner extends Actioner {
    private static $pluginName = 'fondy-payment';
    
    /**
     * Сохраняет  опции плагина
     * @return boolean
     */
    public function saveBaseOption(){
        USER::AccessOnly('1,4','exit()');
        $this->messageSucces = $this->lang['SAVE_BASE'];
        $this->messageError = $this->lang['NOT_SAVE_BASE'];
        unset($_SESSION['fondy-paymentAdmin']);
        unset($_SESSION['fondy-payment']);
        
        if(!empty($_POST['data'])) {
            MG::setOption(array('option' => self::$pluginName.'-option', 'value' => addslashes(serialize($_POST['data']))));
        }
        
        return true;
    }

    public function test(){
        $this->data["message"] = "ok";
		header('location:http://mogunto/payment?id=19&pay=success');
        return true;
    }

    public function notification(){
		if (empty($_POST)) {
            $callback = json_decode(file_get_contents("php://input"));
            $_POST = array();
            foreach ($callback as $key => $val) {
                $_POST[$key] = $val;
            }         
        }  
		$o_id = explode('#', $_POST['order_id'])[1];
		$p_id = $_GET['payment'];
        $result_payment = array();
        $dbRes = DB::query('
            SELECT *
            FROM `'.PREFIX.'payment`
            WHERE `id` = \''.$p_id.'\'');
        $result_payment = DB::fetchArray($dbRes);
		$paymentParamDecoded = json_decode($result_payment[3]);
		foreach ($paymentParamDecoded as $key => $value) {	
            if ($key == 'Язык страницы оплаты') {
                $fondySettings['lan'] = CRYPT::mgDecrypt($value);
            } elseif ($key == "ID Магазина") {
                $fondySettings['merchant_id'] = CRYPT::mgDecrypt($value);
            } elseif ($key == "Секретный ключ") {
                $fondySettings['secret_key'] = CRYPT::mgDecrypt($value);
            }
        }
        $this->data["result"] = $o_id;  

        if ($this->isPaymentValid($fondySettings,$_POST) == true and $_POST['order_status'] == 'approved'){
            $result_order = array();
            $dbRes = DB::query('
                SELECT *
                FROM `'.PREFIX.'order`
                WHERE `id`=\''.$o_id.'\'
            ');

            $result_order = DB::fetchAssoc($dbRes);
            if ($result_order["paided"] == 0 && $result_order["status_id"] != 2){
                $sql = '
                    UPDATE `'.PREFIX.'order` 
                    SET `paided` = 1, `status_id` = 2
                    WHERE `id` = \''.$o_id.'\'';
                DB::query($sql);

                $this->data["result"] = "ok";
            }
			$this->data["message"] = "ok";
			
            return true;
        } else {
            return false;
        }
    }


    public function getPayLink(){
        $p_id = $_POST['paymentId'];
        $mgBaseDir = $_POST['mgBaseDir'];

        $result_payment = array();
        $dbRes = DB::query('
            SELECT *
            FROM `'.PREFIX.'payment`
            WHERE `id` = \''.$p_id.'\'');
        $result_payment = DB::fetchArray($dbRes);

        $result_order = array();
        $dbRes = DB::query('
            SELECT *
            FROM `'.PREFIX.'order`
            WHERE `payment_id`=\''.$p_id.'\' 
			ORDER BY id DESC LIMIT 1
        ');
		
        $result_order = DB::fetchAssoc($dbRes);
		
        $paymentParamDecoded = json_decode($result_payment[3]);
		$o_id = $result_order['id'];
		if (isset($result_order['delivery_cost']) and $result_order['delivery_cost'] > 0){
			$summ = $result_order['summ'] + $result_order['delivery_cost'];
		}else{
			$summ = $result_order['summ'];
		}
        if (strpos($summ, ".") !== false){
            $new_summ = str_replace(".", "", $summ);
            $summ = $new_summ;
        } else {
            $summ = $summ.'00';
        }

        $auth_key_array = array("", "");

        $curr = (MG::getSetting('currencyShopIso')=="RUR")?"RUB":MG::getSetting('currencyShopIso');

        foreach ($paymentParamDecoded as $key => $value) {	
            if ($key == 'Язык страницы оплаты') {
                $lang = CRYPT::mgDecrypt($value);
            } elseif ($key == "ID Магазина") {
                $merchant_id = CRYPT::mgDecrypt($value);
            } elseif ($key == "Секретный ключ") {
                $secret_key = CRYPT::mgDecrypt($value);
            }
        }

        $urlDecoded = json_decode($result_payment[4]);
        foreach ($urlDecoded as $key => $value) {
            if ($key == "result URL:"){
                $resultURL = $value;
            } elseif ($key == "success URL:") {
                $successURL = $value;
            } elseif ($key == "fail URL:") {
                $failURL = $value;
            }
        }
		if($successURL == '')
			$successURL = $mgBaseDir;
        $notificationURL = "/ajaxrequest?mguniqueurl=action/notification&pluginHandler=fondy-payment&orderID=".$o_id."&payment=".$p_id;

        $authToken = base64_encode($auth_key_array[0].$auth_key_array[1]);
		
        $data = array(
			'order_id' => time(). '#' . $o_id,
			'order_desc' => 'Оплата заказа:'. '#' . $o_id,
			'amount' => $summ,
			'lang' =>$lang,
			'currency' => $curr,
			'merchant_id' => $merchant_id,
			'response_url' => $mgBaseDir.$successURL,
			'server_callback_url' => $mgBaseDir.$notificationURL
		);
		$data['signature'] = $this->getSignature($data, $secret_key);
	
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, 'https://api.fondy.eu/api/checkout/url/');
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-type: application/json'));
		//curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-type: application/x-www-form-urlencoded'));
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(array('request' => $data)));
		//curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
		$result = curl_exec($ch);


		$out = (json_decode($result,TRUE));
	
        $this->data["result"] = $out['response']['checkout_url'];
        return true;
    }
	public function getSignature($data, $password, $encoded = true)
		{
			$data = array_filter($data, function ($var) {
				return $var !== '' && $var !== null;
			});
			ksort($data);
			$str = $password;
			foreach ($data as $k => $v) {
				$str .= '|' . $v;
			}
			if ($encoded) {
				return sha1($str);
			} else {
				return $str;
			}
		}
	public function isPaymentValid($fondySettings, $response){	
			if ($fondySettings['merchant_id'] != $response['merchant_id']) {
				return false;
			}
			if ($response['order_status'] != 'approved') {
				return false;
			}
			$responseSignature = $response['signature'];
			if (isset($response['response_signature_string'])){
				unset($response['response_signature_string']);
			}
			if (isset($response['signature'])){
				unset($response['signature']);
			}
			if ($this->getSignature($response, $fondySettings['secret_key']) != $responseSignature) {
				return false;
			}
			return true;
		}	
}
