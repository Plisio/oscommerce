<?php

require('includes/application_top.php');

function verifyCallbackData($post, $apiKey)
{
    if (!isset($post['verify_hash'])) {
        return false;
    }

    $verifyHash = $post['verify_hash'];
    unset($post['verify_hash']);
    ksort($post);
    if (isset($post['expire_utc'])){
        $post['expire_utc'] = (string)$post['expire_utc'];
    }
    if (isset($post['tx_urls'])){
        $post['tx_urls'] = html_entity_decode($post['tx_urls']);
    }
    $postString = serialize($post);
    $checkKey = hash_hmac('sha1', $postString, $apiKey);
    if ($checkKey != $verifyHash) {
        return false;
    }

    return true;
}

global $db;

if (verifyCallbackData($_POST, MODULE_PAYMENT_PLISIO_API_KEY)) {
$order_id = $_REQUEST['order_number'];

$order = tep_db_query("select orders_id from " . TABLE_ORDERS . " where orders_id = '" . intval($order_id) . "' limit 1");
if (tep_db_num_rows($order) <= 0) {
    throw new Exception('Order #' . $order_id . ' does not exists');
}

switch ($_REQUEST['status']) {
    case 'completed':
    case 'mismatch':
        $pl_order_status = MODULE_PAYMENT_PLISIO_PAID_STATUS_ID;
        break;
    case 'cancelled':
        $pl_order_status = MODULE_PAYMENT_PLISIO_CANCELLED_STATUS_ID;
        break;
    case 'expired':
        $pl_order_status = MODULE_PAYMENT_PLISIO_EXPIRED_STATUS_ID;
        break;
    case 'new':
        $pl_order_status = MODULE_PAYMENT_PLISIO_PENDING_STATUS_ID;
        break;
    default:
        $pl_order_status = NULL;
}

if ($pl_order_status) {
    tep_db_query("update " . TABLE_ORDERS . " set orders_status = " . $pl_order_status . " where orders_id = " . intval($order_id));
}
    echo 'OK';
} else {
    echo 'Verify callback data failed';
}
