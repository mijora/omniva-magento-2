<?php

namespace Omnivalt\Shipping\Model\Helper;

class ShippingMethod {
    
    private $methods_map = [
        'c' => 'Courier',
        'cp' => 'Courier plus',
        'pt' => 'Parcel terminal',
        'po' => 'Post office',
        'pc' => 'Private customer',
    ];

    private $shipping_sets = array(
        'classic' => array(
            'pt pt' => 'PA',
            'pt c' => 'PK',
            'c pt' => 'PU',
            'c c' => 'QH',
            'po pt' => 'PV',
            'courier_call' => 'QH',
        ),
        'estonia' => array(
            'pt pt' => 'PA',
            'pt po' => 'PO',
            'pt c' => 'PK',
            'c pt' => 'PU',
            'c c' => 'CI',
            'c cp' => 'LX', //not sure
            'po cp' => 'LH',
            'po pt' => 'PV',
            'po po' => 'CD',
            'po c' => 'CE',
            'lc pt' => 'PP',
            'courier_call' => 'CI',
        ),
        'finland' => array(
            'c pc' => 'QB', //QB in documentation
            'c po' => 'CD', //not sure
            'c c' => 'CE', //not sure
            'pt pt' => 'CD',
            'c pt' => 'CD',
            'courier_call' => 'CE',
        ),
    );
    private $shipping_params = array(
        'LT' => array(
            'title' => 'Lithuania',
            'methods' => array('pickup', 'courier'),
            'shipping_sets' => array(
                'LT' => 'classic',
                'LV' => 'classic',
                'EE' => 'classic',
                'call' => 'classic',
            ),
            'comment_lang' => 'lit',
            'tracking_url' => 'https://www.omniva.lt/verslo/siuntos_sekimas?barcode=',
        ),
        'LV' => array(
            'title' => 'Latvia',
            'methods' => array('pickup', 'courier'),
            'shipping_sets' => array(
                'LT' => 'classic',
                'LV' => 'classic',
                'EE' => 'classic',
                'FI' => 'finland',
                'call' => 'classic',
            ),
            'comment_lang' => 'lav',
            'tracking_url' => 'https://www.omniva.lv/privats/sutijuma_atrasanas_vieta?barcode=',
        ),
        'EE' => array(
            'title' => 'Estonia',
            'methods' => array('pickup', 'courier', 'courier_plus'),
            'shipping_sets' => array(
                'LT' => 'classic',
                'LV' => 'classic',
                'EE' => 'estonia',
                'FI' => 'finland',
                'call' => 'estonia',
            ),
            'comment_lang' => 'est',
            'tracking_url' => 'https://www.omniva.ee/era/jalgimine?barcode=',
        ),
        'FI' => array(
            'title' => 'Finland',
            'methods' => array('pickup', 'courier', 'private_customer'),
            'shipping_sets' => array(
                'LT' => 'estonia',
                'LV' => 'estonia',
                'EE' => 'estonia',
                'FI' => 'finland',
                'call' => 'estonia',
            ),
            'comment_lang' => '',
            'tracking_url' => '',
        ),
    );
    private $additional_services = array(
        /*
        'arrival_sms' => array(
            'title' => 'Arrival SMS',
            'code' => 'ST',
            'only_for' => array('PA', 'PU', 'PP', 'PO', 'PV', 'CD', 'CE', 'LX', 'LH'),
            'in_product' => false,
            'in_order' => false,
            'add_always' => true,
        ),
        'arrival_email' => array(
            'title' => 'Arrival email',
            'code' => 'SF',
            'only_for' => array('PA', 'PU', 'PP', 'PO', 'PV', 'CD', 'CE', 'LX', 'LH'),
            'in_product' => false,
            'in_order' => 'checkbox',
            'add_always' => false,
        ),*/
        'fragile' => array(
            'title' => 'Fragile',
            'code' => 'BC',
            'only_for' => 'all',
            'in_product' => 'checkbox',
            'in_order' => 'checkbox',
            'add_always' => false,
            'desc_product' => 'If this item will be added to the shipment, mark that shipment as fragile',
        ),
        'private_customer' => array(
            'title' => 'Delivery to private customer',
            'code' => 'CL',
            'only_for' => array('CI'),
            'in_product' => false,
            'in_order' => 'checkbox',
            'add_always' => false,
        ),
        'doc_return' => array(
            'title' => 'Document return',
            'code' => 'XT',
            'only_for' => array('LA', 'LE', 'LZ', 'LG', 'LX', 'LH', 'CI', 'QK', 'QP', 'LL', 'CE', 'CD', 'CB', 'QH', 'QL'),
            'in_product' => false,
            'in_order' => 'checkbox',
            'add_always' => false,
        ),
        'paid_by_receiver' => array(
            'title' => 'Paid by receiver',
            'code' => 'BS',
            'only_for' => array('LX', 'LH'),
            'in_product' => false,
            'in_order' => 'checkbox',
            'add_always' => false,
        ),
        'insurance' => array(
            'title' => 'Insurance',
            'code' => 'BI',
            'only_for' => array('LX', 'LH', 'QB', 'CE', 'CD'),
            'in_product' => false,
            'in_order' => 'checkbox',
            'add_always' => false,
        ),
        'personal_delivery' => array(
            'title' => 'Personal delivery',
            'code' => 'BK',
            'only_for' => array('LX', 'LH', 'CE', 'CD'),
            'in_product' => false,
            'in_order' => 'checkbox',
            'add_always' => false,
        ),
        'paid_parcel_sms' => array(
            'title' => 'Paid parcel SMS',
            'code' => 'GN',
            'only_for' => array('CE', 'CD'),
            'in_product' => false,
            'in_order' => 'checkbox',
            'add_always' => false,
        ),
        'paid_parcel_email' => array(
            'title' => 'Paid parcel email',
            'code' => 'GM',
            'only_for' => array('CE', 'CD'),
            'in_product' => false,
            'in_order' => 'checkbox',
            'add_always' => false,
        ),
        'return_notification_sms' => array(
            'title' => 'Return notification SMS',
            'code' => 'SB',
            'only_for' => array('CE', 'CD', 'LX', 'LH'),
            'in_product' => false,
            'in_order' => 'checkbox',
            'add_always' => false,
        ),
        'return_notification_email' => array(
            'title' => 'Return notification email',
            'code' => 'SG',
            'only_for' => array('CE', 'CD', 'LX', 'LH'),
            'in_product' => false,
            'in_order' => 'checkbox',
            'add_always' => false,
        ),
        'persons_over_18' => array(
            'title' => 'Issue to persons at the age of 18+',
            'code' => 'PC',
            'only_for' => array('CE', 'CD', 'PA', 'PU', 'PK', 'QH'),
            'in_product' => false,
            'in_order' => 'checkbox',
            'add_always' => false,
        ),
        'delivery_confirmation_sms' => array(
            'title' => 'Delivery confirmation SMS to sender',
            'code' => 'SS',
            'only_for' => array('LX', 'LH'),
            'in_product' => false,
            'in_order' => 'checkbox',
            'add_always' => false,
        ),
        'delivery_confirmation_email' => array(
            'title' => 'Delivery confirmation e-mail to sender',
            'code' => 'SE',
            'only_for' => array('LX', 'LH'),
            'in_product' => false,
            'in_order' => 'checkbox',
            'add_always' => false,
        ),
    );
    private $services_by_country = array(
        'LX' => [
            'sender_countries' => [
                'EE'
            ],
        ],
        'LH' => [
            'sender_countries' => [
                'EE'
            ],
        ],
        'CD' => [
            'sender_countries' => [
                'EE'
            ],
        ],
        'CE' => [
            'sender_countries' => [
                'EE'
            ],
        ],
        'QB' => [
            'sender_countries' => [
                'EE'
            ],
        ],
    );
    
    public function getAdditionalServices($order, $omniva){
        $services = [];
        $api_country = $omniva->getConfigData('company_countrycode');
        //$receiver_country = $order->getCountry();
        $send_method_name = trim($order->getShippingMethod());
        $send_method = 'c';
        if (strtolower($send_method_name) == 'omnivalt_parcel_terminal') {
            $send_method = 'pt';
        } else if (strtolower($send_method_name) == 'omnivalt_courier_plus') {
            $send_method = 'cp';
        }
        $service = $this->getShippingService($omniva, $send_method, $order);
        //echo $service;
        if ($service){
            foreach ($this->additional_services as $key => $additional_service){
                if (isset($this->services_by_country[$additional_service['code']])){
                    if (!in_array($api_country, $this->services_by_country[$additional_service['code']]['sender_countries'] )){
                        continue;
                    }
                }
                if (!is_array($additional_service['only_for']) || in_array($service, $additional_service['only_for'])){
                    array_push($services, array(
                        'title' => $additional_service['title'],
                        'value' => $additional_service['code']
                    ));
                }
            }
        }
        return $services;
    }
    
    public function getAdditionalServicesCodes($services){
        $codes = array();
        if (is_array($services)){
            foreach ($services as $service){
                if (isset($this->additional_services[$service])){
                    $codes[] = $this->additional_services[$service]['code'];
                }
            }
        }
        return $codes;
    }
    
    public function getShippingMethodTitle($order){
        $options = $order->getOptions();
        if (empty($options['send_method']) && empty($order->getTerminal())) return false;
        $default = ($order->getTerminal() == 1) ? 'c': 'pt';
        $method = $options['send_method'] ?? $default;
        if ($method == 'pt' && $order->getTerminal() === '1') {
            $method = 'c';
        }
        if ($method && isset($this->methods_map[$method])){
            return $this->methods_map[$method];
        }
        return false;
    }
    
    public function getShippingService($omniva, $send_method, $order) {
        $service = false;
        try {
            $api_country = $omniva->getConfigData('company_countrycode');;
            $pickup_method = $omniva->getConfigData('pickup') ?? 'c';
            if ($pickup_method == 'PARCEL_TERMINAL') {
                $pickup_method = 'pt';
            } else {
                $pickup_method = 'c';
            }
            $shippingAddress = $order->getShippingAddress();
            $receiver_country = $shippingAddress->getCountryId();
            if (isset($this->shipping_params[$api_country]) && isset($this->shipping_params[$api_country]['shipping_sets'][$receiver_country])) {
                $set_name = $this->shipping_params[$api_country]['shipping_sets'][$receiver_country];
                $method_pair = $pickup_method . ' ' . $send_method;
                if (isset($this->shipping_sets[$set_name])) {
                    $service_mapping = $this->shipping_sets[$set_name];
                    $service = $service_mapping[$method_pair] ?? false;
                    if (!isset($service_mapping[$method_pair])){
                        $txt_sender = (isset($this->methods_map[$pickup_method])) ? $this->methods_map[$pickup_method] : $pickup_method;
                        $txt_receiver = (isset($this->methods_map[$send_method])) ? $this->methods_map[$send_method] : $send_method;
                        return array('error' => 'Send parcels from ' . $txt_sender . ' to ' . $txt_receiver . ' is not allowed');
                    }
                } else {
                    return array('error' => 'Params for receiver is not found');
                }
            } else {
                return array('error' => 'Params for API country code "' . $api_country . '" is not found');
            }
        } catch(\Exception $e){
            return array('error' => 'An unknown error occurred');
        }
        return $service;
    }

    public function getSendMethod($settings, $shipping_code) {
        try {
            if (isset($settings->pt_method_key) && stripos($shipping_code, $settings->pt_method_key) !== false || stripos($shipping_code, 'omniva_terminal') !== false) {
                return 'pt';
            }
            if (isset($settings->cp_method_key) && stripos($shipping_code, $settings->cp_method_key) !== false || stripos($shipping_code, 'omniva_courierplus') !== false) {
                return 'cp';
            }
            if (isset($settings->pc_method_key) && stripos($shipping_code, $settings->pc_method_key) !== false || stripos($shipping_code, 'omniva_privatecustomer') !== false) {
                return 'pc';
            }
            if (isset($settings->c_method_key) && stripos($shipping_code, $settings->c_method_key) !== false || stripos($shipping_code, 'omniva_courier') !== false) {
                return 'c';
            }
            if (isset($settings->pc_method_key) && stripos($shipping_code, $settings->pc_method_key) !== false || stripos($shipping_code, 'omniva_postoffice') !== false) {
                return 'po';
            }
        } catch(\Exception $e){
            
        }
        return false;
    }

    public function getCallCourierService($settings) {
        $service = false;
        $api_country = strtoupper($settings->api_country ?? $settings->shop_country);
        if (isset($this->shipping_params[$api_country]) && isset($this->shipping_params[$api_country]['shipping_sets']['call'])) {
            $set_name = $this->shipping_params[$api_country]['shipping_sets']['call'];
            if (isset($this->shipping_sets[$set_name])) {
                $service_mapping = $this->shipping_sets[$set_name];
                $method_pair = 'courier_call';
                $service = $service_mapping[$method_pair] ?? false;
            }
        }
        return $service;
    }

}