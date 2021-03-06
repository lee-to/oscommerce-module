<?php

require ('includes/application_top.php');

function callbackHandler($data)
{
    $method = '';
    $params = array();
    if ((isset($data['params'])) && (isset($data['method'])) && (isset($data['params']['signature']))){
        $params = $data['params'];
        $method = $data['method'];
        $signature = $params['signature'];
        if (empty($signature)){
            $status_sign = false;
        }else{
            $status_sign = verifySignature($params, $method);
        }
    }else{
        $status_sign = false;
    }
//    $status_sign = true;
    if ($status_sign){
        switch ($method) {
            case 'check':
                $result = check( $params );
                break;
            case 'pay':
                $result = pay( $params );
                break;
            case 'error':
                $result = error( $params );
                break;
            default:
                $result = array('error' =>
                    array('message' => 'неверный метод')
                );
                break;
        }
    }else{
        $result = array('error' =>
            array('message' => 'неверная сигнатура')
        );
    }
    hardReturnJson($result);
}
function check( $params )
{
    $order_id = $params['account'];
    $order_query = tep_db_query($sql="select orders_status, currency, currency_value from " . TABLE_ORDERS . " where orders_id = '" . $order_id . "'");

    if (!tep_db_num_rows($order_query)) {
        $result = array('error' =>
            array('message' => 'заказа не существует')
        );
    }else{

        $order = tep_db_fetch_array($order_query);
        $total_query = tep_db_query("select value from " . TABLE_ORDERS_TOTAL . " where orders_id = '" . $order_id . "' and class = 'ot_total' limit 1");
        $total = tep_db_fetch_array($total_query);

        if ((float)$total['value'] != (float)$params['orderSum']) {
            $result = array('error' =>
                array('message' => 'не совпадает сумма заказа')
            );
        }elseif ($order['currency'] != $params['orderCurrency']) {
            $result = array('error' =>
                array('message' => 'не совпадает валюта заказа')
            );
        }
        else{
            $result = array('result' =>
                array('message' => 'Запрос успешно обработан')
            );
        }
    }

    return $result;
}
function pay( $params )
{
    $order_id = $params['account'];
    $order_query = tep_db_query($sql="select orders_status, currency, currency_value from " . TABLE_ORDERS . " where orders_id = '" . $order_id . "'");
    if (!tep_db_num_rows($order_query)) {
        $result = array('error' =>
            array('message' => 'заказа не существует')
        );
    }else{

        $order = tep_db_fetch_array($order_query);
        $total_query = tep_db_query("select value from " . TABLE_ORDERS_TOTAL . " where orders_id = '" . $order_id . "' and class = 'ot_total' limit 1");
        $total = tep_db_fetch_array($total_query);

        if ((float)$total['value'] != (float)$params['orderSum']) {
            $result = array('error' =>
                array('message' => 'не совпадает сумма заказа')
            );
        }elseif ($order['currency'] != $params['orderCurrency']) {
            $result = array('error' =>
                array('message' => 'не совпадает валюта заказа')
            );
        }
        else{

            $order_status_id = (MODULE_PAYMENT_UNITPAY_ORDER_PAY_STATUS_ID > 0 ? (int)MODULE_PAYMENT_UNITPAY_ORDER_PAY_STATUS_ID : (int)DEFAULT_ORDERS_STATUS_ID);

            tep_db_query($sql="update " . TABLE_ORDERS . " set orders_status = '" . $order_status_id . "', last_modified = now() where orders_id = '" . $order_id . "'");
            $sql_data_array = array('orders_id' => $order_id,
                'orders_status_id' => $order_status_id,
                'date_added' => 'now()',
                'customer_notified' => '0',
                'comments' => 'Unitpay payment is succesful');
            tep_db_perform(TABLE_ORDERS_STATUS_HISTORY, $sql_data_array);

            $result = array('result' =>
                array('message' => 'Запрос успешно обработан')
            );
        }
    }
    return $result;
}
function error( $params )
{
    $order_id = $params['account'];
    $order_query = tep_db_query($sql="select orders_status, currency, currency_value from " . TABLE_ORDERS . " where orders_id = '" . $order_id . "'");
    if (!tep_db_num_rows($order_query)) {
        $result = array('error' =>
            array('message' => 'заказа не существует')
        );
    }
    else{

        $order_status_id = (MODULE_PAYMENT_UNITPAY_ORDER_ERROR_STATUS_ID > 0 ? (int)MODULE_PAYMENT_UNITPAY_ORDER_ERROR_STATUS_ID : (int)DEFAULT_ORDERS_STATUS_ID);

        tep_db_query($sql="update " . TABLE_ORDERS . " set orders_status = '" . $order_status_id . "', last_modified = now() where orders_id = '" . $order_id . "'");
        $sql_data_array = array('orders_id' => $order_id,
            'orders_status_id' => $order_status_id,
            'date_added' => 'now()',
            'customer_notified' => '0',
            'comments' => 'Unitpay payment is succesful');
        tep_db_perform(TABLE_ORDERS_STATUS_HISTORY, $sql_data_array);

        $result = array('result' =>
            array('message' => 'Запрос успешно обработан')
        );
    }
    return $result;
}
function getSignature($method, array $params, $secretKey)
{
    ksort($params);
    unset($params['sign']);
    unset($params['signature']);
    array_push($params, $secretKey);
    array_unshift($params, $method);
    return hash('sha256', join('{up}', $params));
}
function verifySignature($params, $method)
{
    $secret = MODULE_PAYMENT_UNITPAY_SECRET_KEY;
    return $params['signature'] == getSignature($method, $params, $secret);
}
function hardReturnJson( $arr )
{
    header('Content-Type: application/json');
    $result = json_encode($arr);
    die($result);
}
callbackHandler($_GET);