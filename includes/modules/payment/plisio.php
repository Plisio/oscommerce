<?php

class plisio
{
    public $code;
    public $title;
    public $description;
    public $enabled;

    function plisio()
    {
        $this->code             = 'plisio';
        $this->title            = MODULE_PAYMENT_PLISIO_TEXT_TITLE;
        $this->description      = MODULE_PAYMENT_PLISIO_TEXT_DESCRIPTION;
        $this->api_key          = MODULE_PAYMENT_PLISIO_API_KEY;
        $this->sort_order       = MODULE_PAYMENT_PLISIO_SORT_ORDER;
        $this->enabled          = ((MODULE_PAYMENT_PLISIO_STATUS == 'True') ? true : false);
    }

    function javascript_validation()
    {
        return false;
    }

    function selection()
    {
        return array('id' => $this->code, 'module' => $this->title);
    }

    function pre_confirmation_check()
    {
        return false;
    }

    function confirmation()
    {
        return false;
    }

    function process_button()
    {
        return false;
    }

    function before_process()
    {
        return false;
    }

    function after_process()
    {
        require_once(dirname(__FILE__) . "/Plisio/version.php");
        require_once(dirname(__FILE__) . "/Plisio/Lib/PlisioClient.php");
        global $insert_id, $order;

        $info = $order->info;

        $callback = tep_href_link('plisio_callback.php', $parameters='', $connection='NONSSL', $add_session_id=true, $search_engine_safe=true, $static=true );

        $client = new PlisioClient\PlisioClient(MODULE_PAYMENT_PLISIO_API_KEY);
        $params = array(
            'order_name' => 'Order #' . $insert_id,
            'order_number' => $insert_id,
            'source_amount' => number_format($info['total'], 2, '.', ''),
            'source_currency' => $info['currency'],
            'callback_url' => $callback,
            'cancel_url' => tep_href_link(FILENAME_CHECKOUT_PAYMENT),
            'success_url' => tep_href_link(FILENAME_CHECKOUT_SUCCESS),
            'email' => $order->customer['email_address'],
            'plugin' => 'OSCommerce',
            'version' => PLISIO_OSCOMMERCE_EXTENSION_VERSION
        );

        $response = $client->createTransaction($params);
        if ($response && $response['status'] !== 'error' && !empty($response['data'])) {
            $_SESSION['cart']->reset(true);
            tep_redirect($response['data']['invoice_url']);
        } else {
            tep_redirect(tep_href_link(FILENAME_CHECKOUT_PAYMENT, 'payment_error=' . $this->code . '&error=' . implode(',', json_decode($response['data']['message'], true)), 'SSL'));
        }
    }

    function get_error()
    {
        global $HTTP_GET_VARS;
        return array('title' => MODULE_PAYMENT_PLISIO_ORDER_ERROR_TITLE,
            'error' => $HTTP_GET_VARS['error']);
    }

    function check()
    {
        if (!isset($this->_check)) {
            $check_query  = tep_db_query("select configuration_value from " . TABLE_CONFIGURATION . " where configuration_key = 'MODULE_PAYMENT_PLISIO_STATUS'");
            $this->_check = tep_db_num_rows($check_query);
        }

        return $this->_check;
    }

    function install()
    {
        $status_query = tep_db_query("select max(orders_status_id) as status_id from " . TABLE_ORDERS_STATUS);
        $status = tep_db_fetch_array($status_query);

        $status_id = $status['status_id']+1;
        $status_id_paid = $status_id;
        $status_id_pending = $status_id + 1;
        $status_id_expired = $status_id + 2;
        $status_id_cancelled = $status_id + 3;

        $languages = tep_get_languages();

        foreach ($languages as $lang) {
            tep_db_query("insert into " . TABLE_ORDERS_STATUS . " (orders_status_id, language_id, orders_status_name) values ('" . $status_id_paid . "', '" . $lang['id'] . "', 'Plisio [Paid]')");
            tep_db_query("insert into " . TABLE_ORDERS_STATUS . " (orders_status_id, language_id, orders_status_name) values ('" . $status_id_pending . "', '" . $lang['id'] . "', 'Plisio [Pending]')");
            tep_db_query("insert into " . TABLE_ORDERS_STATUS . " (orders_status_id, language_id, orders_status_name) values ('" . $status_id_expired . "', '" . $lang['id'] . "', 'Plisio [Expired]')");
            tep_db_query("insert into " . TABLE_ORDERS_STATUS . " (orders_status_id, language_id, orders_status_name) values ('" . $status_id_cancelled . "', '" . $lang['id'] . "', 'Plisio [Cancelled]')");
        }

        $flags_query = tep_db_query("describe " . TABLE_ORDERS_STATUS . " public_flag");
        if (tep_db_num_rows($flags_query) == 1) {
            tep_db_query("update " . TABLE_ORDERS_STATUS . " set public_flag = 0 and downloads_flag = 0 where orders_status_id = '" . $status_id_paid . "'");
            tep_db_query("update " . TABLE_ORDERS_STATUS . " set public_flag = 0 and downloads_flag = 0 where orders_status_id = '" . $status_id_pending . "'");
            tep_db_query("update " . TABLE_ORDERS_STATUS . " set public_flag = 0 and downloads_flag = 0 where orders_status_id = '" . $status_id_expired . "'");
            tep_db_query("update " . TABLE_ORDERS_STATUS . " set public_flag = 0 and downloads_flag = 0 where orders_status_id = '" . $status_id_cancelled . "'");
        }

        tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('Enable Plisio Module', 'MODULE_PAYMENT_PLISIO_STATUS', 'False', 'Enable Plisio Payment Gateway plugin?', '6', '0', 'tep_cfg_select_option(array(\'True\', \'False\'), ', now())");
        tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Plisio API Key', 'MODULE_PAYMENT_PLISIO_API_KEY', '0', 'Your Plisio API Key', '6', '0', now())");
        tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Sort order of display.', 'MODULE_PAYMENT_PLISIO_SORT_ORDER', '0', 'Sort order of display. Lowest is displayed first.', '6', '8', now())");
        tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, use_function, date_added) values ('Set Plisio Order Status when order is pending', 'MODULE_PAYMENT_PLISIO_PENDING_STATUS_ID', '" . $status_id_pending .  "', 'Status in your store when order is pending.<br />(\'Plisio [Pending]\' recommended)', '6', '5', 'tep_cfg_pull_down_order_statuses(', 'tep_get_order_status_name', now())");
        tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, use_function, date_added) values ('Set Plisio Order Status when order is cancelled', 'MODULE_PAYMENT_PLISIO_CANCELLED_STATUS_ID', '" . $status_id_cancelled .  "', 'Status in your store when order is cancelled.<br />(\'Plisio [Cancelled]\' recommended)', '6', '5', 'tep_cfg_pull_down_order_statuses(', 'tep_get_order_status_name', now())");
        tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, use_function, date_added) values ('Set Plisio Order Status when order is expired', 'MODULE_PAYMENT_PLISIO_EXPIRED_STATUS_ID', '" . $status_id_expired .  "', 'Status in your store when order is expired.<br />(\'Plisio [Expired]\' recommended)', '6', '5', 'tep_cfg_pull_down_order_statuses(', 'tep_get_order_status_name', now())");
        tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, use_function, date_added) values ('Set Plisio Order Status when order is paid', 'MODULE_PAYMENT_PLISIO_PAID_STATUS_ID', '" . $status_id_paid .  "', 'Status in your store when order is paid.<br />(\'Plisio [Paid]\' recommended)', '6', '5', 'tep_cfg_pull_down_order_statuses(', 'tep_get_order_status_name', now())");
    }

    function remove ()
    {
        tep_db_query("delete from " . TABLE_CONFIGURATION . " where configuration_key LIKE 'MODULE\_PAYMENT\_PLISIO\_%'");
        tep_db_query("delete from " . TABLE_ORDERS_STATUS . " where LOWER(orders_status_name) LIKE '%plisio%'");
    }

    function keys()
    {
        return array(
            'MODULE_PAYMENT_PLISIO_STATUS',
            'MODULE_PAYMENT_PLISIO_API_KEY',
            'MODULE_PAYMENT_PLISIO_SORT_ORDER',
            'MODULE_PAYMENT_PLISIO_PENDING_STATUS_ID',
            'MODULE_PAYMENT_PLISIO_PAID_STATUS_ID',
            'MODULE_PAYMENT_PLISIO_CANCELLED_STATUS_ID',
            'MODULE_PAYMENT_PLISIO_EXPIRED_STATUS_ID',
        );
    }
}
