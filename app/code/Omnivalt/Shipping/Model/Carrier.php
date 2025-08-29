<?php

/**
 * Copyright © 2013-2017 Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
// @codingStandardsIgnoreFile

namespace Omnivalt\Shipping\Model;

use Magento\Framework\Module\ModuleListInterface;
use Magento\Framework\Module\Dir;
use Magento\Framework\Xml\Security;
use Magento\Quote\Model\Quote\Address\RateRequest;
use Magento\Shipping\Model\Carrier\AbstractCarrierOnline;
use Magento\Shipping\Model\Rate\Result;
use Magento\Shipping\Model\Tracking\Result as TrackingResult;

use Mijora\Omniva\OmnivaException;
use Mijora\Omniva\Shipment\Package\AdditionalService;
use Mijora\Omniva\Shipment\Package\Address;
use Mijora\Omniva\Shipment\Package\Contact;
use Mijora\Omniva\Shipment\Package\Measures;
use Mijora\Omniva\Shipment\Package\Cod;
use Mijora\Omniva\Shipment\Package\Package;
use Mijora\Omniva\Shipment\Package\ServicePackage;
use Mijora\Omniva\Shipment\Shipment;
use Mijora\Omniva\Shipment\ShipmentHeader;
use Mijora\Omniva\Locations\PickupPoints;
use Mijora\Omniva\Shipment\Label;
use Mijora\Omniva\Shipment\Tracking;
use Mijora\Omniva\Shipment\CallCourier;
use Mijora\Omniva\ServicePackageHelper\ServicePackageHelper;
use Omnivalt\Shipping\Model\LabelHistoryFactory;
use Omnivalt\Shipping\Model\CourierRequestFactory;

/**
 * Omnivalt shipping implementation
 *
 * @author Magento Core Team <core@magentocommerce.com>
 * @SuppressWarnings(PHPMD.ExcessiveClassComplexity)
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class Carrier extends AbstractCarrierOnline implements \Magento\Shipping\Model\Carrier\CarrierInterface
{
    /**
     * Module name
     * 
     * @var string
     */
    const MODULE = 'Omnivalt_Shipping';

    /**
     * Code of the carrier
     *
     * @var string
     */
    const CODE = 'omnivalt';


    /**
     * Code of the carrier
     *
     * @var string
     */
    protected $_code = self::CODE;

    /**
     * Types of rates, order is important
     *
     * @var array
     */
    protected $_ratesOrder = [
        'RATED_ACCOUNT_PACKAGE',
        'PAYOR_ACCOUNT_PACKAGE',
        'RATED_ACCOUNT_SHIPMENT',
        'PAYOR_ACCOUNT_SHIPMENT',
        'RATED_LIST_PACKAGE',
        'PAYOR_LIST_PACKAGE',
        'RATED_LIST_SHIPMENT',
        'PAYOR_LIST_SHIPMENT',
    ];

    /**
     * @var ModuleListInterface
     */
    protected $_moduleList;

    /**
     * Rate request data
     *
     * @var RateRequest|null
     */
    protected $_request = null;

    /**
     * Rate result data
     *
     * @var Result|TrackingResult
     */
    protected $_result = null;

    /**
     * Path to wsdl file of rate service
     *
     * @var string
     */
    protected $_rateServiceWsdl;

    /**
     * Path to wsdl file of ship service
     *
     * @var string
     */
    protected $_shipServiceWsdl = null;

    /**
     * Path to wsdl file of track service
     *
     * @var string
     */
    protected $_trackServiceWsdl = null;

    /**
     * Path to locations xml
     *
     * @var string
     */
    protected $_locationFile;

    /**
     * Container types that could be customized for Omnivalt carrier
     *
     * @var string[]
     */
    protected $_customizableContainerTypes = ['YOUR_PACKAGING'];

    /**
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    protected $_storeManager;

    /**
     * @var \Magento\Catalog\Model\ResourceModel\Product\CollectionFactory
     */
    protected $_productCollectionFactory;

    /**
     * @inheritdoc
     */
    protected $_debugReplacePrivateDataKeys = [
        'Account', 'Password'
    ];

    /**
     * Version of tracking service
     * @var int
     */
    private static $trackServiceVersion = 10;

    /**
     * List of TrackReply errors
     * @var array
     */
    private static $trackingErrors = ['FAILURE', 'ERROR'];

    /**
     * @var \Magento\Framework\Xml\Parser
     */
    private $XMLparser;
    protected $configWriter;

    /**
     * Session instance reference
     * 
     */
    protected $_checkoutSession;
    protected $variableFactory;
    protected $omnivaPickupPoints;
    protected $labelhistoryFactory;
    protected $courierRequestFactory;
    protected $shipping_helper;

    /**
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     * @param \Magento\Quote\Model\Quote\Address\RateResult\ErrorFactory $rateErrorFactory
     * @param \Psr\Log\LoggerInterface $logger
     * @param Security $xmlSecurity
     * @param \Magento\Shipping\Model\Simplexml\ElementFactory $xmlElFactory
     * @param \Magento\Shipping\Model\Rate\ResultFactory $rateFactory
     * @param \Magento\Quote\Model\Quote\Address\RateResult\MethodFactory $rateMethodFactory
     * @param \Magento\Shipping\Model\Tracking\ResultFactory $trackFactory
     * @param \Magento\Shipping\Model\Tracking\Result\ErrorFactory $trackErrorFactory
     * @param \Magento\Shipping\Model\Tracking\Result\StatusFactory $trackStatusFactory
     * @param \Magento\Directory\Model\RegionFactory $regionFactory
     * @param \Magento\Directory\Model\CountryFactory $countryFactory
     * @param \Magento\Directory\Model\CurrencyFactory $currencyFactory
     * @param \Magento\Directory\Helper\Data $directoryData
     * @param \Magento\CatalogInventory\Api\StockRegistryInterface $stockRegistry
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     * @param \Magento\Framework\Module\Dir\Reader $configReader
     * @param \Magento\Catalog\Model\ResourceModel\Product\CollectionFactory $productCollectionFactory
     * @param array $data
     *
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     */
    public function __construct(
            ModuleListInterface $moduleList,
            \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
            \Magento\Quote\Model\Quote\Address\RateResult\ErrorFactory $rateErrorFactory,
            \Psr\Log\LoggerInterface $logger,
            Security $xmlSecurity,
            \Magento\Shipping\Model\Simplexml\ElementFactory $xmlElFactory,
            \Magento\Shipping\Model\Rate\ResultFactory $rateFactory,
            \Magento\Quote\Model\Quote\Address\RateResult\MethodFactory $rateMethodFactory,
            \Magento\Shipping\Model\Tracking\ResultFactory $trackFactory,
            \Magento\Shipping\Model\Tracking\Result\ErrorFactory $trackErrorFactory,
            \Magento\Shipping\Model\Tracking\Result\StatusFactory $trackStatusFactory,
            \Magento\Directory\Model\RegionFactory $regionFactory,
            \Magento\Directory\Model\CountryFactory $countryFactory,
            \Magento\Directory\Model\CurrencyFactory $currencyFactory,
            \Magento\Directory\Helper\Data $directoryData,
            \Magento\CatalogInventory\Api\StockRegistryInterface $stockRegistry,
            \Magento\Store\Model\StoreManagerInterface $storeManager,
            \Magento\Framework\Module\Dir\Reader $configReader,
            \Magento\Catalog\Model\ResourceModel\Product\CollectionFactory $productCollectionFactory,
            \Magento\Framework\Xml\Parser $parser,
            \Magento\Framework\App\Config\Storage\WriterInterface $configWriter,
            \Magento\Checkout\Model\Session $checkoutSession,
            \Magento\Variable\Model\VariableFactory $variableFactory,
            PickupPoints $omnivaPickupPoints,
            LabelHistoryFactory $labelhistoryFactory,
            CourierRequestFactory $courierRequestFactory,
            \Omnivalt\Shipping\Model\Helper\ShippingMethod $shipping_helper,
            array $data = []
    ) {
        $this->_checkoutSession = $checkoutSession;
        $this->_moduleList = $moduleList;
        $this->_storeManager = $storeManager;
        $this->_productCollectionFactory = $productCollectionFactory;
        $this->XMLparser = $parser;
        $this->variableFactory = $variableFactory;
        $this->omnivaPickupPoints = $omnivaPickupPoints;
        $this->labelhistoryFactory = $labelhistoryFactory;
        $this->courierRequestFactory = $courierRequestFactory;
        $this->shipping_helper = $shipping_helper;
        parent::__construct(
                $scopeConfig,
                $rateErrorFactory,
                $logger,
                $xmlSecurity,
                $xmlElFactory,
                $rateFactory,
                $rateMethodFactory,
                $trackFactory,
                $trackErrorFactory,
                $trackStatusFactory,
                $regionFactory,
                $countryFactory,
                $currencyFactory,
                $directoryData,
                $stockRegistry,
                $data
        );

        if (!defined('_OMNIVA_INTEGRATION_AGENT_ID_')) {
            define('_OMNIVA_INTEGRATION_AGENT_ID_', '7005511 Magento v' . $this->getModuleVersion());
        }

        //check terminals list
        $this->_locationFile = $configReader->getModuleDir(Dir::MODULE_ETC_DIR, 'Omnivalt_Shipping') . '/locations.json';
        try {
            $var = $this->variableFactory->create();
            $var->loadByCode('OMNIVA_REFRESH');
            if (!$var->getId() || $var->getPlainValue() < time() - 3600 * 24 || !file_exists($this->_locationFile)) {
                //$omnivaLocs = $this->omnivaPickupPoints->getFilteredLocations();
                $omnivaLocs = file_get_contents('https://omniva.ee/locationsfull.json');
                if ($omnivaLocs) {
                    $this->omnivaPickupPoints->saveLocationsToJSONFile($this->_locationFile, $omnivaLocs);
                    if (!$var->getId()) {
                        $var->setData(['code' => 'OMNIVA_REFRESH',
                            'plain_value' => time()
                        ]);
                    } else {
                        $var->addData(['plain_value' => time()]);
                    }
                    $var->save();
                }
            }
        } catch (\Exception $e) {
            
        }
    }

    /**
     * Get module version
     * 
     * @return string
     */
    public function getModuleVersion()
    {
        return $this->_moduleList->getOne(self::MODULE)['setup_version'] ?? '0.0.0';
    }

    /**
     * Collect and get rates
     *
     * @param RateRequest $request
     * @return Result|bool|null
     */
    public function collectRates(RateRequest $request) {
        if (!$this->getConfigFlag('active')) {
            return false;
        }

        $result = $this->_rateFactory->create();
        //$packageValue = $request->getBaseCurrency()->convert($request->getPackageValueWithDiscount(), $request->getPackageCurrency());
        $packageValue = $request->getPackageValueWithDiscount();
        $packageWeight = $request->getPackageWeight();
        $this->_updateFreeMethodQuote($request);
        $isFreeEnabled = $this->getConfigData('free_shipping_enable');
        $allowedMethods = explode(',', $this->getConfigData('allowed_methods'));
        $company_country = $this->getConfigData('company_countrycode');
        $max_weight_c = $this->getConfigData('max_package_weight');
        $max_weight_pt = $this->getConfigData('max_package_weight_pt');
        
        $country_id = $request->getDestCountryId();
        
        if (!$country_id) {
            $country_id = $this->_checkoutSession->getQuote()
                ->getShippingAddress()
                ->getCountryId();
        }

        foreach ($allowedMethods as $allowedMethod) {
            $method = $this->_rateMethodFactory->create();

            $method->setCarrier('omnivalt');

            $method->setMethod($allowedMethod);
            $method->setMethodTitle($this->getCode('method', $allowedMethod));
            $amount = false;
            $freeFrom = false;
            $title = $this->getConfigData('title');

            if ($allowedMethod == "COURIER") {
                if (!empty($max_weight_c) && $packageWeight > floatval($max_weight_c)) {
                    continue;
                }
                switch ($country_id) {
                    case 'LV':
                        $amount = $this->getConfigData('priceLV_C');
                        $freeFrom = $this->getConfigData('lv_courier_free_shipping_subtotal');
                        break;
                    case 'EE':
                        $amount = $this->getConfigData('priceEE_C');
                        $freeFrom = $this->getConfigData('ee_courier_free_shipping_subtotal');
                        break;
                    case 'FI':
                        $amount = $this->getConfigData('priceFI_C');
                        $freeFrom = $this->getConfigData('fi_courier_free_shipping_subtotal');
                        $title = $this->getConfigData('title_matkahuolto');
                        break;
                    case 'LT':
                        $amount = $this->getConfigData('price');
                        $freeFrom = $this->getConfigData('lt_courier_free_shipping_subtotal');
                }
            }
            if ($allowedMethod == "PARCEL_TERMINAL") {
                if (!empty($max_weight_pt) && $packageWeight > floatval($max_weight_pt)) {
                    continue;
                }
                switch ($country_id) {
                    case 'LV':
                        $amount = $this->getConfigData('priceLV_pt');
                        $freeFrom = $this->getConfigData('lv_parcel_terminal_free_shipping_subtotal');
                        break;
                    case 'EE':
                        $amount = $this->getConfigData('priceEE_pt');
                        $freeFrom = $this->getConfigData('ee_parcel_terminal_free_shipping_subtotal');
                        break;
                    case 'FI':
                        $amount = $this->getConfigData('priceFI_pt');
                        $freeFrom = $this->getConfigData('fi_parcel_terminal_free_shipping_subtotal');
                        $title = $this->getConfigData('title_matkahuolto');
                        break;
                    case 'LT':
                        $amount = $this->getConfigData('price2');
                        $freeFrom = $this->getConfigData('lt_parcel_terminal_free_shipping_subtotal');
                }
            }
            if ($allowedMethod == "COURIER_PLUS") {
                if ($country_id == "EE" && ($company_country == 'EE' || $company_country == 'FI')) {
                    $amount = $this->getConfigData('priceEE_CP');
                    $freeFrom = $this->getConfigData('ee_courier_plus_free_shipping_subtotal');
                } else {
                    continue;
                }
            }

            if ($isFreeEnabled && $packageValue >= $freeFrom && $freeFrom >= 0 && $freeFrom != '') {
                $amount = 0;
            }

            if ($amount === false) {
                continue;
            }

            $method->setCarrierTitle($title);
            $method->setPrice($amount);
            $method->setCost($amount);
            $result->append($method);
        }

        // Intenational shipping settings
        if (in_array('INTERNATIONAL', $allowedMethods) && $country_service = ServicePackageHelper::getCountryOptions($country_id)) {
            foreach ($country_service['package'] as $service => $service_data) {
                if ($service_data['maxWeightKg'] < $packageWeight) {
                    continue;
                }
                $amount = $this->getConfigData('int_' . $service);
                if (!$amount && $amount != 0) {
                    continue;
                }
                $method = $this->_rateMethodFactory->create();
                $method->setCarrier('omnivalt');
                $title = $this->getConfigData('title');
                $method->setMethod('INTERNATIONAL_' . strtoupper($service));
                $method->setMethodTitle($this->getCode('method', $allowedMethod) . ' ' . strtoupper($service));
                $method->setCarrierTitle($title);
                $method->setPrice($amount);
                $method->setCost($amount);
                $result->append($method);
            }
        }

        return $result;
    }

    /**
     * Get version of rates request
     *
     * @return array
     */
    public function getVersionInfo() {
        return ['ServiceId' => 'crs', 'Major' => '10', 'Intermediate' => '0', 'Minor' => '0'];
    }

    /**
     * Get configuration data of carrier
     *
     * @param string $type
     * @param string $code
     * @return array|false
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     */
    public function getCode($type, $code = '') {

        $codes = [
            'method' => [
                'COURIER' => __('Courier'),
                'PARCEL_TERMINAL' => __('Parcel terminal'),
                'COURIER_PLUS' => __('Courier Plus'),
                'INTERNATIONAL' => __('International'),
            ],
            'country' => $this->getCountriesList(),
            'tracking' => [
            ],
            'terminal' => [],
        ];
        if ($type == 'terminal') {
            $locations = [];
            $locationsArray = $this->omnivaPickupPoints->loadLocationsFromJSONFile($this->_locationFile);
            foreach ($locationsArray as $loc_data) {
                if ((int) $loc_data['TYPE'] !== 1 && (float) $loc_data['X_COORDINATE'] > 0 && (float) $loc_data['Y_COORDINATE'] > 0) {
                    $locations[$loc_data['ZIP']] = array(
                        'name' => $loc_data['NAME'],
                        'country' => $loc_data['A0_NAME'],
                        'x' => $loc_data['X_COORDINATE'],
                    );
                }
            }
            $codes['terminal'] = $locations;
        }


        if (!isset($codes[$type])) {
            return false;
        } elseif ('' === $code) {
            return $codes[$type];
        }

        if (!isset($codes[$type][$code])) {
            return false;
        } else {
            return $codes[$type][$code];
        }
    }

    private function getCountriesList($with_baltic = true) {
        $services = ServicePackageHelper::getServices();
        if ($with_baltic) {
            $countires = [
                'EE' => __('Estonia'),
                'LV' => __('Latvia'),
                'LT' => __('Lithuania'),
                'FI' => __('Finland')
            ];
        } else {
            $countires = [];
        }
        foreach ($services as $service) {
            $countires[$service['iso']] = $service['country'];
        }
        return $countires;
    }

    public function getTerminalAddress($terminal_id) {
        if (file_exists($this->_locationFile) && $terminal_id) {
            $locationsArray = $this->omnivaPickupPoints->loadLocationsFromJSONFile($this->_locationFile);
            foreach ($locationsArray as $loc_data) {
                if ($loc_data['ZIP'] == $terminal_id) {
                    $parcel_terminal_address = $loc_data['NAME'] . ', ' . $loc_data['A2_NAME'] . ', ' . $loc_data['A0_NAME'];
                    return $parcel_terminal_address;
                }
            }
        }
        return '';
    }

    public function getTerminals($countryCode = null) {
        $terminals = array();
        if (file_exists($this->_locationFile)) {
            $locationsArray = $this->omnivaPickupPoints->loadLocationsFromJSONFile($this->_locationFile);
            foreach ($locationsArray as $loc_data) {
                if (($loc_data['A0_NAME'] == $countryCode || $countryCode == null) && ((int) $loc_data['TYPE'] !== 1 && (float) $loc_data['X_COORDINATE'] > 0 && (float) $loc_data['Y_COORDINATE'] > 0)) {
                    $terminals[] = $loc_data;
                }
            }
        }
        //var_dump($terminals); exit;
        return $terminals;
    }

    /**
     * Get tracking
     *
     * @param string|string[] $trackings
     * @return Result|null
     */
    public function getTracking($trackings) {

        $result = $this->_trackFactory->create();
        if (!is_array($trackings)) {
            $trackings = [$trackings];
        }
        $resultArr = [];
        try {
            $username = $this->getConfigData('account');
            $password = $this->getConfigData('password');
            
            $api_tracking = new Tracking();
            $api_tracking->setAuth($username, $password);

            $results = $api_tracking->getTrackingOmx($trackings[0]);

            if (is_array($results)) {
                $packageProgress = [];
                foreach ($results as $event) {
                    $shipmentEventArray = [];
                    $shipmentEventArray['activity'] = $event['eventName'];
                    $shipmentEventArray['deliverydate'] = date('Y-m-d', strtotime($event['eventDate']));
                    $shipmentEventArray['deliverytime'] = date('H:i:s', strtotime($event['eventDate']));
                    $shipmentEventArray['deliverylocation'] = isset($event['location']['locationName']) ? $event['location']['locationName'] : '-';
                    $packageProgress[] = $shipmentEventArray;
                }
                $resultArr[$trackings[0]] = ['progressdetail' => $packageProgress];
            }

            if (!empty($resultArr)) {
                foreach ($resultArr as $trackNum => $data) {
                    $tracking = $this->_trackStatusFactory->create();
                    $tracking->setCarrier($this->_code);
                    $tracking->setCarrierTitle($this->getConfigData('title'));
                    $tracking->setTracking($trackNum);
                    $tracking->addData($data);
                    $result->append($tracking);
                }
            }
        } catch (\Exception $e) {
            
        }
        //$this->_getXMLTracking($trackings);

        return $result;
    }

    /**
     * Get allowed shipping methods
     *
     * @return array
     */
    public function getAllowedMethods() {
        $allowed = explode(',', $this->getConfigData('allowed_methods'));
        $arr = [];
        foreach ($allowed as $k) {
            $arr[$k] = $this->getCode('method', $k);
        }

        return $arr;
    }

    public function cancelOmnivaPickup($id) {
        try {
            $username = $this->getConfigData('account');
            $password = $this->getConfigData('password');

            $call = new CallCourier();
            $call->setAuth($username, $password);
            $is_canceled = $call->cancelCourierOmx($id);
            return $is_canceled ? true : false;
        } catch (\Exception $e) {
            
        }
        return false;
    }

    private function setUtcTime($time) {
        return gmdate('H:i', strtotime($time));
    }

    public function callOmniva() {
        try {
            $username = $this->getConfigData('account');
            $password = $this->getConfigData('password');
            $api_url = $this->getConfigData('production_webservices_url');
            
            $pickStart = $this->getConfigData('pick_up_time_start')?$this->getConfigData('pick_up_time_start'):'8:00';
            $pickFinish = $this->getConfigData('pick_up_time_finish')?$this->getConfigData('pick_up_time_finish'):'17:00';

            $name = $this->getConfigData('cod_company');
            $phone = $this->getConfigData('company_phone');
            $street = $this->getConfigData('company_address');
            $postcode = $this->getConfigData('company_postcode');
            $city = $this->getConfigData('company_city');
            $country = $this->getConfigData('company_countrycode');

            $address = new Address();
            $address
                    ->setCountry($country)
                    ->setPostcode($postcode)
                    ->setDeliverypoint($city)
                    ->setStreet($street);

            // Sender contact data
            $senderContact = new Contact();
            $senderContact
                    ->setAddress($address)
                    ->setMobile($phone)
                    ->setPersonName($name);

            $call = new CallCourier();
            $call
                ->setAuth($username, $password)
                ->setSender($senderContact)
                ->setEarliestPickupTime($this->setUtcTime($pickStart))
                ->setLatestPickupTime($this->setUtcTime($pickFinish))
                ->setComment('');
            $call_result = $call->callCourier();
            if ($call->getResponseCallNumber()) {
                $model = $this->courierRequestFactory->create();
                $data = [
                    'omniva_request_id' => $call->getResponseCallNumber()
                ];
                $model->setData($data);
                $model->save();
                return true;
            } else {
                return false;
            }
        } catch (\Exception $e) {

        }
        return false;
    }

    protected function getReferenceNumber($order_number) {
        $order_number = (string) $order_number;
        $kaal = array(7, 3, 1);
        $sl = $st = strlen($order_number);
        $total = 0;
        while ($sl > 0 and substr($order_number, --$sl, 1) >= '0') {
            $total += substr($order_number, ($st - 1) - $sl, 1) * $kaal[($sl % 3)];
        }
        $kontrollnr = ((ceil(($total / 10)) * 10) - $total);
        return $order_number . $kontrollnr;
    }

    /**
     * Receive tracking number and labels.
     *
     * @param Array $barcodes
     * @return \Magento\Framework\DataObject
     */
    protected function _getShipmentLabels($barcodes) {

        $result = new \Magento\Framework\DataObject();
        try {
            $username = $this->getConfigData('account');
            $password = $this->getConfigData('password');
            $api_url = $this->getConfigData('production_webservices_url');

            $label = new Label();
            $label->setAuth($username, $password, $api_url);
            $labels = $label->downloadLabels($barcodes, false, 'S');
            if ($labels) {
                $result->setShippingLabelContent($labels);
                $result->setTrackingNumber(is_array($barcodes) ? $barcodes[0] : $barcodes);
            } else {
                $result->setErrors(sprintf(__('Labels not received for barcodes: %s'), implode(', ', $barcodes)));
            }
        } catch (\Exception $e) {
            $result->setErrors($e->getMessage());
        }
        return $result;
    }
    
    public function getLabels($barcodes) {
        try {
            $username = $this->getConfigData('account');
            $password = $this->getConfigData('password');
            $api_url = $this->getConfigData('production_webservices_url');

            $label = new Label();
            $label->setAuth($username, $password, $api_url);
            $combine = $this->getConfigData('combine_labels');
            $labels = $label->downloadLabels($barcodes, $combine, 'I');
            if ($labels) {
                
            } else {
                
            }
        } catch (\Exception $e) {
            
        }
    }

    /**
     * Do shipment request to carrier web service, obtain Print Shipping Labels and process errors in response
     *
     * @param \Magento\Framework\DataObject $request
     * @return \Magento\Framework\DataObject
     * @throws \Exception
     */
    protected function _doShipmentRequest(\Magento\Framework\DataObject $request) {
        $barcodes = array();
        $this->_prepareShipmentRequest($request);
        $result = new \Magento\Framework\DataObject();

        try {
            $order_shipment = $request->getOrderShipment();
            $order = $order_shipment->getOrder();
            $order_items = $order_shipment->getAllItems();
            $username = $this->getConfigData('account');
            $password = $this->getConfigData('password');
            $api_url = $this->getConfigData('production_webservices_url');

            $name = $this->getConfigData('cod_company');
            $phone = $this->getConfigData('company_phone');
            $street = $this->getConfigData('company_address');
            $postcode = $this->getConfigData('company_postcode');
            $city = $this->getConfigData('company_city');
            $country = $this->getConfigData('company_countrycode');
            $bank_account = $this->getConfigData('cod_bank_account');
            $send_return_code = $this->getConfigData('send_return_code');

            $receiver_country = $request->getRecipientAddressCountryCode();

            $payment_method = $order->getPayment()->getMethodInstance()->getCode();
            $is_cod = in_array($payment_method, ['cashondelivery', 'msp_cashondelivery']);

            $send_method_name = trim($request->getShippingMethod());
            $pickup_method = $this->getConfigData('pickup');
            
            $send_method = 'c';
            if (strtolower($send_method_name) == 'parcel_terminal') {
                $send_method = 'pt';
            } else if (strtolower($send_method_name) == 'courier_plus') {
                $send_method = 'cp';
            }

            if ( $is_cod && $receiver_country == 'FI' && strtolower($send_method_name) == 'parcel_terminal' ) {
                $result->setErrors('Additional service COD is not available in this country');
                return $result;
            }
            $is_international = (stripos($send_method_name, 'international') !== false);
            $service = false;
            if (!$is_international) {
                $service = $this->shipping_helper->getShippingService($this, $send_method, $order);
                
                //in case cannot get correct service
                if ($service === false || is_array($service)) {
                    switch ($pickup_method . ' ' . $send_method_name) {
                        case 'COURIER PARCEL_TERMINAL':
                            $service = "PU";
                            break;
                        case 'COURIER COURIER':
                            $service = "QH";
                            break;
                        case 'PARCEL_TERMINAL COURIER':
                            $service = "PK";
                            break;
                        case 'PARCEL_TERMINAL PARCEL_TERMINAL':
                            $service = "PA";
                            break;
                        default:
                            $service = "";
                            break;
                    }
                }
            }
            $is_terminal = $send_method_name == 'PARCEL_TERMINAL';

            $content_desription = $this->getContentDescription($order_items);

            $shipment = new Shipment();
            /*
              $shipment
              ->setComment('Test comment')
              ->setShowReturnCodeEmail(true);
             */
            $shipmentHeader = new ShipmentHeader();
            $shipmentHeader
                    ->setSenderCd($username)
                    ->setFileId(date('Ymdhis'));
            $shipment->setShipmentHeader($shipmentHeader)
                ->setComment('');
                       
            $additionalServices = [];
            if ($service == "PA" || $service == "PU" || $service == 'CD') {
                $additionalServices[] = (new AdditionalService())->setServiceCode('ST');
                if ($is_cod) {
                    $additionalServices[] = (new AdditionalService())->setServiceCode('BP');
                }
            }
            // set fragile or/adn 18+ service
            $this->setOrderServices($order);
            $is_fragile = false;
            $_orderServices = json_decode($order->getOmnivaltServices() ?? '[]', true);
            if (isset($_orderServices['services']) && is_array($_orderServices['services'])) {
                foreach ($_orderServices['services'] as $_service) {
                    $additionalServices[] = (new AdditionalService())->setServiceCode($_service);
                    if ($_service == 'BC') {
                        $is_fragile = true;
                    }
                }
            }
            $measures = new Measures();
            $measures->setWeight($request->getPackageWeight())
            /*
              ->setVolume(9)
              ->setHeight(2)
              ->setWidth(3); */;

              
            // Receiver contact data
            $receiverContact = new Contact();
            $address = new Address();
            $address
                    ->setCountry($receiver_country)
                    ->setPostcode($request->getRecipientAddressPostalCode())
                    ->setDeliverypoint($request->getRecipientAddressCity())
                    ->setStreet($request->getRecipientAddressStreet1());
            if ($send_method_name === 'PARCEL_TERMINAL') {
                $address->setOffloadPostcode($order->getShippingAddress()->getOmnivaltParcelTerminal());
            }
            $receiverContact
                    ->setAddress($address)
                    ->setMobile($request->getRecipientContactPhoneNumber())
                    ->setPersonName($request->getRecipientContactPersonName());

            // Sender contact data
            $sender_address = new Address();
            $sender_address
                    ->setCountry($country)
                    ->setPostcode($postcode)
                    ->setDeliverypoint($city)
                    ->setStreet($street);
            $senderContact = new Contact();
            $senderContact
                    ->setAddress($sender_address)
                    ->setMobile($phone)
                    ->setPersonName($name);
            
            $labels_count = isset($_orderServices['labels_count']) ? $_orderServices['labels_count'] : 1;

            $packages = [];
            for ($i=0; $i<$labels_count; $i++) {
            
                $package_id = $order->getId();
                if ($is_terminal || !$is_cod) {
                    $package_id .= '-' . $i;
                }
                $package = new Package();
                if ($is_international) {
                    $package
                        ->setId($package_id . '-' . $i)
                        ->setService(Package::MAIN_SERVICE_PARCEL, Package::CHANNEL_COURIER)
                        ->setServicePackage(
                            (new ServicePackage(str_ireplace('international_', '', $send_method_name)))
                        )
                        ->setContentDescription($content_desription);
                } else {
                    $package
                        ->setId($package_id)
                        ->setService($service);
                }
                if ($send_return_code) {
                    $package->setReturnAllowed(true);
                }
                if ($i == 0 || $is_terminal || !$is_cod) {        
                    $package->setAdditionalServices($additionalServices);
                } elseif ($is_fragile) {
                    $package->setAdditionalServices([(new AdditionalService())->setServiceCode('BC')]);
                }

                $package->setMeasures($measures);

                //set COD
                if ($is_cod && ($i == 0 || $is_terminal)) {
                    $cod = new Cod();
                    $cod
                            ->setAmount(round($request->getOrderShipment()->getOrder()->getGrandTotal(), 2))
                            ->setBankAccount($bank_account)
                            ->setReceiverName($name)
                            ->setReferenceNumber($this->getReferenceNumber($order->getId()));
                    $package->setCod($cod);
                }
                $package->setReceiverContact($receiverContact);

                $package->setSenderContact($senderContact);
                
                $packages[] = $package;
            }
            $shipment->setPackages($packages);

            //set auth data
            $shipment->setAuth($username, $password, $api_url);
            $shipment_result = $shipment->registerShipment();
            if (isset($shipment_result['barcodes'])) {
                foreach ($shipment_result['barcodes'] as $_barcode) {
                    $this->createLabelHistory($order, $_barcode, $service);
                }
                return $this->_getShipmentLabels($shipment_result['barcodes']);
            } else {
                $result->setErrors(__('No saved barcodes received'));
            }
        } catch (OmnivaException $e) {
            $result->setErrors($e->getMessage());
        }
        return $result;
    }

    private function getContentDescription($order_items) {
        $items_names = array();
        foreach ($order_items as $item) {
            $order_item = $item->getOrderItem();

            $qty = $order_item->getQtyOrdered();
            $qty_formatted = rtrim(rtrim(number_format($qty, 4, '.', ''), '0'), '.');
            $name = $order_item->getName();
            $name = substr($name, 0, 31);
            $items_names[] = $qty_formatted . '×' . trim($name);
        }
        return implode('; ', $items_names);
    }

    /**
     * @param array|object $trackingIds
     * @return string
     */
    private function getTrackingNumber($trackingIds) {
        return is_array($trackingIds) ? array_map(
                        function ($val) {
                            return $val->TrackingNumber;
                        },
                        $trackingIds
                ) : $trackingIds->TrackingNumber;
    }

    /**
     * For multi package shipments. Delete requested shipments if the current shipment
     * request is failed
     *
     * @param array $data
     * @return bool
     */
    public function rollBack($data) {
        
    }

    /**
     * Return delivery confirmation types of carrier
     *
     * @param \Magento\Framework\DataObject|null $params
     * @return array
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function getDeliveryConfirmationTypes(\Magento\Framework\DataObject $params = null) {
        return $this->getCode('delivery_confirmation_types');
    }

    /**
     * Recursive replace sensitive fields in debug data by the mask
     * @param array $data
     * @return string
     */
    protected function filterDebugData($data) {
        foreach (array_keys($data) as $key) {
            if (is_array($data[$key])) {
                $data[$key] = $this->filterDebugData($data[$key]);
            } elseif (in_array($key, $this->_debugReplacePrivateDataKeys)) {
                $data[$key] = self::DEBUG_KEYS_MASK;
            }
        }
        return $data;
    }
    
    public function createLabelHistory($order, $barcode, $services = '') {
        try {
            $model = $this->labelhistoryFactory->create();
            $data = [
                'order_id' => $order->getId(),
                'label_barcode' => $barcode,
                'services' => $services,
            ];
            $model->setData($data);
            $model->save();
            return true;
        } catch (\Exception $e) {
            
        }
        return false;
    }

    public function setOrderServices($order) {
        if ($order->getOmnivaltServices() == null) {
            $services = [];
            if ($this->getConfigData('fragile')) {
                $services[] = 'BC';
            }
            if ($this->hasAttributeProducts($order)) {
                $services[] = 'PC';
                if (!in_array('BC', $services)) {
                    $services[] = 'BC';
                }
            }
            $order->setOmnivaltServices(json_encode(array('services'=>$services)));
            $order->save();
        }
    }

    public function hasAttributeProducts($order) {
        $attr_code = $this->getConfigData('attr_code');
        if (!$attr_code) {
            return false;
        }
        $has = false;
        foreach ($order->getAllItems() as $item) {
            $attr = $item->getProduct()->getCustomAttribute($attr_code);
            if ($attr && $attr->getValue() == '1') {
                $has = true;
                break;
            }
        }
        return $has;
    }

}
