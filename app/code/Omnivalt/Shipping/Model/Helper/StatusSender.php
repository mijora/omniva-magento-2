<?php

namespace Omnivalt\Shipping\Model\Helper;

class StatusSender {

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
      "ordersCountSince" => date('Y-m-d', strtotime("first day of previous month")),
      "sendingTimestamp" => date('Y-m-d H:i:s'),
      "setPricing" => [
          "LT" => [
              "country" => "LT",
              "courier" => $this->getConfigData('price'),
              "terminal" => $this->getConfigData('price2'),
          ],
          "LV" => [
              "country" => "LV",
              "courier" => $this->getConfigData('priceLV_C'),
              "terminal" => $this->getConfigData('priceLV_pt'),
          ],
          "EE" => [
              "country" => "EE",
              "courier" => $this->getConfigData('priceEE_C'),
              "terminal" => $this->getConfigData('priceEE_pt'),
          ],
          "FI" => [
              "country" => "FI",
              "courier" => $this->getConfigData('priceFI_C'),
              "terminal" => $this->getConfigData('priceFI_pt'),
          ],
      ]
    ];
    return $data;
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
      \Magento\Store\Model\ScopeInterface::SCOPE_STORE, 
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