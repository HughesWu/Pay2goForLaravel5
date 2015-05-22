<?php
/**
 * 適用各模組的 Pay2go 核心
 *
 * @author HughesWu
 */
class Pay2goPaymentClass {

    function __construct() {

    }

    /**
     * 取得 CheckValue
     * 
     * @param array     $paymentParams      MPG 參數; (Version: 版本;)
     * @param String    $hash_key           商店 Key
     * @param String    $hash_iv            商店 Iv
     */
    public function get_check_value ($paymentParams, $hash_key, $hash_iv) {

        // 重新排序參數
        $sortArray  =   ($paymentParams["Version"] == "1.0")

                    //  1.0
                    ? array (
                        "MerchantID"        =>  $paymentParams["MerchantID"],
                        "TimeStamp"         =>  $paymentParams["TimeStamp"],
                        "Version"           =>  $paymentParams['Version'],
                        "RespondType"       =>  $paymentParams['RespondType'],
                    )

                    //  1.1
                    : array (
                        "MerchantID"        =>  $paymentParams["MerchantID"],
                        "TimeStamp"         =>  $paymentParams["TimeStamp"],
                        "MerchantOrderNo"   =>  $paymentParams["MerchantOrderNo"],
                        "Version"           =>  $paymentParams["Version"],
                        "Amt"               =>  $paymentParams["Amt"],
                    );

        ksort($sortArray);

        $check_merstr = http_build_query($sortArray);

        $checkValue_str = "HashKey=" . $hash_key . "&" . $check_merstr . "&HashIV=" . $hash_iv;

        return strtoupper(hash("sha256", $checkValue_str)); 
    }

    /**
     * 產生 Form
     * 
     * @param array     $paymentParams      MPG 參數; 預設為"空"; 資料格式為一維陣列 Key = Input 的 Name, Value = Input 的 Value
     * @param array     $paymentMethod      付款方式; 預設為"否"; 資料格式為一維陣列 Key = Input 的 Name, Value = Input 的 Value
     * @param boolean   $isTest             是否為測試模式; 預設為"是"
     * @param boolean   $autoSubmit         是否自動送出; 預設為"否"; 當此參數為"是", 會自動清除 $submitButtonStyle
     * @param string    $submitButtonStyle  送出按鈕格式; 預設為"空"
     */
    public function create_form ($paymentParams = NULL, $paymentMethod = NULL, $isTest = TRUE, $autoSubmit = FALSE, $autoSubmitBySec = 0, $submitButtonStyle = NULL) {

        $check_data = $this->check_params($paymentParams);

        if (count($check_data) == 0) {
        
            //  自動送出時, 會自動清除 $submitButtonStyle
            $submitButtonStyle = ($autoSubmit == TRUE) ? "" : $submitButtonStyle;

            $form   =   "<form action='".$this->form_url($isTest)."' method='POST' id='Pay2goMPGForm' name='Pay2goMPGForm'>";

            //  MPG 參數
            if (!empty($paymentParams) && is_array($paymentParams)) {
                foreach($paymentParams as $name => $value){
                    $form   .=  "<input type='hidden' name='".$name."' value='".$value."'>";
                }
            }

            //  指定付款方式 (此處會自動將 Input 的 Name 轉大寫)
            if (!empty($paymentMethod) && is_array($paymentMethod)) {
                foreach($paymentMethod as $name => $value){
                    $form   .=  "<input type='hidden' name='".strtoupper($name)."' value='".$value."'>";
                }
            }

            //  送出按鈕格式
            if (!empty($submitButtonStyle)) {
                $form .= $submitButtonStyle;
            }

            $form  .=   "</form>";

            //  自動送出
            if ($autoSubmit) {
                $form  .=   "<Script Language='javascript'>document.forms.Pay2goMPGForm.submit();</Script>";
            }
            
            //  幾秒後送出
            if ($autoSubmitBySec > 0) {
                $sec = $autoSubmitBySec * 1000;
                $form  .=   "<Script Language='javascript'>setTimeout('document.Pay2goMPGForm.submit()', ".$sec.");</Script>";
            }            

            return $form;

        } else {
            return $check_data;
        }
    }

    /**
     * 各項參數檢查
     * 
     * @param array     $paymentParams  MPG 參數
     */
    public function check_params ($paymentParams) {

        $errArray = array ();

        if (!isset($paymentParams["MerchantID"]) || empty($paymentParams["MerchantID"])) {
            $errArray[] = "商店代號 設定錯誤或是空白";
        }

        if (!isset($paymentParams["RespondType"]) || !in_array($paymentParams["RespondType"], array("String", "JSON"))) {
            $errArray[] = "回傳格式 設定錯誤或是空白";
        }

        if (!isset($paymentParams["CheckValue"]) || empty($paymentParams["CheckValue"])) {
            $errArray[] = "檢查碼 設定錯誤或是空白";
        }

        if (!isset($paymentParams["TimeStamp"]) || empty($paymentParams["TimeStamp"])) {
            $errArray[] = "時間戳記 設定錯誤或是空白";
        }

        if (!isset($paymentParams["Version"]) || !in_array($paymentParams["Version"], array("1.0", "1.1"))) {
            $errArray[] = "串接版本 設定錯誤或是空白";
        }

        if (!isset($paymentParams["MerchantOrderNo"]) || empty($paymentParams["MerchantOrderNo"])) {
            $errArray[] = "商店訂單編號 設定錯誤或是空白";
        } else if (strlen($paymentParams["MerchantOrderNo"]) > 20) {
            $errArray[] = "商店訂單編號 長度不得超過 20 字元";
        } else if (!preg_match("/^[a-zA-Z0-9_]+$/", $paymentParams["MerchantOrderNo"])) {
            $errArray[] = "商店訂單編號 僅為英數字 + 底線格式";
        }

        if (!isset($paymentParams["Amt"]) || empty($paymentParams["Amt"])) {
            $errArray[] = "訂單金額  設定錯誤或是空白";
        } else if (strlen($paymentParams["Amt"]) > 10) {
            $errArray[] = "訂單金額  長度不得超過 10 字元";
        } else if (!preg_match("/^[0-9]+$/", $paymentParams["Amt"])) {
            $errArray[] = "訂單金額  僅為數字格式";
        }

        if (!isset($paymentParams["ItemDesc"]) || empty($paymentParams["ItemDesc"])) {
            $errArray[] = "商品資訊  設定錯誤或是空白";
        }

        if (!isset($paymentParams["LoginType"]) || (empty($paymentParams["LoginType"]) && $paymentParams["LoginType"] != 0)) {
            $errArray[] = "是否要登入智付寶會員  設定錯誤或是空白";
        }

        if (isset($paymentParams["Email"]) && empty($paymentParams["Email"]) && !preg_match("/[a-zA-Z0-9\._\+]+@([a-zA-Z0-9\.-]\.)*[a-zA-Z0-9\.-]+/", $paymentParams["Email"])) {
            $errArray[] = "付款人電子信箱  設定錯誤";
        }

        return $errArray;
    }

    /**
     * 送至測試 或 正式機
     * 
     * @param boolean $isTest 是否為測試模式 (預設為"是")
     */
    public function form_url ($isTest = TRUE) {
        return ($isTest == TRUE) ? "https://capi.pay2go.com/MPG/mpg_gateway" : "https://api.pay2go.com/MPG/mpg_gateway";
    }
}
?>