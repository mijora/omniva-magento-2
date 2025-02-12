<?php

namespace Omnivalt\Shipping\Model\Helper;

class StatusSender {

  /**
   * PowerBi endpoint
   * LIVE: https://flow.omniva.ee/api/v1/data
   * TEST: https://pre-flow.omniva.ee/api/v1/data
   */
  const ENDPOINT = 'https://flow.omniva.ee/api/v1/data';

  protected $productMetadata;
  protected $scopeConfig;
  protected $_orderCollectionFactory;

  public function __construct(
    \Magento\Framework\App\ProductMetadataInterface $productMetadata,
    \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
    \Magento\Sales\Model\ResourceModel\Order\CollectionFactory $orderCollectionFactory
  ) {
      $this->productMetadata = $productMetadata;
      $this->scopeConfig = $scopeConfig; 
      $this->_orderCollectionFactory = $orderCollectionFactory;
  }

  public function sendStatus() {
    $data = [
      "pluginVersion" => $this->getModuleVersion(),
      "eCommPlatform" => "Magento " . $this->productMetadata->getVersion(),
      "omnivaApiKey"  => $this->getConfigData('account'),
      "senderName" => $this->getConfigData('cod_company'),
      "senderCountryCode" => $this->getConfigData('company_countrycode'),
      "ordersCount" => [
        "courier" => count($this->getAllOrders('courier')),
        "terminal" => count($this->getAllOrders('terminal'))
      ],
      "ordersCountSince" => date('Y-m-d H:i:s', strtotime("first day of previous month")),
      "sendingTimestamp" => date('Y-m-d H:i:s'),
      "setPricing" => [
          "LT" => [
              "country" => "LT",
              "courier" => [
                "min" => $this->getConfigData('price'),
                "max" => $this->getConfigData('price'),
              ],
              "terminal" => [
                "min" => $this->getConfigData('price2'),
                "max" => $this->getConfigData('price2'),
              ],
          ],
          "LV" => [
              "country" => "LV",
              "courier" => [
                "min" => $this->getConfigData('priceLV_C'),
                "max" => $this->getConfigData('priceLV_C'),
              ],
              "terminal" => [
                "min" => $this->getConfigData('priceLV_pt'),
                "max" => $this->getConfigData('priceLV_pt'),
              ],
          ],
          "EE" => [
              "country" => "EE",
              "courier" => [
                "min" => $this->getConfigData('priceEE_C'),
                "max" => $this->getConfigData('priceEE_C'),
              ],
              "terminal" => [
                "min" => $this->getConfigData('priceEE_pt'),
                "max" => $this->getConfigData('priceEE_pt'),
              ],
          ],
          "FI" => [
              "country" => "FI",
              "courier" => [
                "min" => $this->getConfigData('priceFI_C'),
                "max" => $this->getConfigData('priceFI_C'),
              ],
              "terminal" => [
                "min" => $this->getConfigData('priceFI_pt'),
                "max" => $this->getConfigData('priceFI_pt'),
              ],
          ],
      ]
    ];
    return $this->sendToApi($data);
  }

  private function sendToApi($body)
  {
      // Generate request body
      $body = json_encode($body);

      // Default header
      $headers = array(
          "Content-type: application/json;charset=\"utf-8\"",
          "Accept: application/json",
          "Cache-Control: no-cache",
          "Pragma: no-cache",
          "Content-length: " . mb_strlen($body),
      );

      $curl = curl_init();
      curl_setopt_array($curl, [
          CURLOPT_URL => self::ENDPOINT,
          CURLOPT_CUSTOMREQUEST => 'POST',
          CURLOPT_MAXREDIRS => 10,
          CURLOPT_HTTPHEADER => $headers,
          CURLOPT_RETURNTRANSFER => 1,
          CURLOPT_HEADER => 0,
          CURLOPT_TIMEOUT => 10,
          CURLOPT_POSTFIELDS => $body,
      ]);

      $result = curl_exec($curl);

      $http_code = (int) (curl_getinfo($curl, CURLINFO_HTTP_CODE));

      curl_close($curl);
      //return $result;
      // assume succes on 2xx HTTP code
      return 200 <= $http_code && 300 > $http_code;
  }

  private function getModuleVersion() {
    $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
    $resource = $objectManager->get('Magento\Framework\App\ResourceConnection');
    $connection = $resource->getConnection();
    $tableName = $resource->getTableName('setup_module');
    $sql = "Select `schema_version` FROM " . $tableName . " where module = 'Omnivalt_Shipping'";
    $result = $connection->fetchOne($sql);
    return $result;
  }

  private function getConfigData($value) {
    return $this->scopeConfig->getValue( 
      'carriers/omnivalt/' . $value, 
      \Magento\Store\Model\ScopeInterface::SCOPE_STORE
    ); 
  }

  private function getAllOrders($type = '') {
    $collection = $this->_orderCollectionFactory->create()->addFieldToFilter('shipping_method', array('like' => 'omnivalt_%' . $type))->addFieldToFilter('status', array('eq' => 'complete'))->addFieldToFilter('created_at', array('gt' => date('Y-m-d', strtotime("first day of previous month"))))->load();
    $filtered = [];
    foreach($collection as $o) {
      if (count($o->getShipmentsCollection()) > 0) {
        $filtered[] = $o;
      }
    };
    return $filtered;
  }
}