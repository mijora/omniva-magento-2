<?php

namespace Omnivalt\Shipping\Setup;

use Magento\Framework\Setup\UpgradeSchemaInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\SchemaSetupInterface;

class UpgradeSchema implements UpgradeSchemaInterface
{

    /**
     * {@inheritdoc}
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     */
    public function upgrade(SchemaSetupInterface $setup, ModuleContextInterface $context) {
        $installer = $setup;
        $installer->startSetup();

        if (version_compare($context->getVersion(), '1.2.2', '<')) {

            if (!$installer->tableExists('omnivalt_label_history')) {
                $table = $installer->getConnection()->newTable(
                                $installer->getTable('omnivalt_label_history')
                        )
                        ->addColumn(
                                'labelhistory_id',
                                \Magento\Framework\DB\Ddl\Table::TYPE_INTEGER,
                                null,
                                [
                                    'identity' => true,
                                    'nullable' => false,
                                    'primary' => true,
                                    'unsigned' => true,
                                ],
                                'Label history ID'
                        )
                        ->addColumn(
                                'order_id',
                                \Magento\Framework\DB\Ddl\Table::TYPE_INTEGER,
                                null,
                                ['nullable => false'],
                                'Order id'
                        )
                        ->addColumn(
                                'label_barcode',
                                \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
                                255,
                                [],
                                'Label barcode'
                        )
                        ->addColumn(
                                'created_at',
                                \Magento\Framework\DB\Ddl\Table::TYPE_TIMESTAMP,
                                null,
                                ['nullable' => false, 'default' => \Magento\Framework\DB\Ddl\Table::TIMESTAMP_INIT],
                                'Created At'
                        )->addColumn(
                                'updated_at',
                                \Magento\Framework\DB\Ddl\Table::TYPE_TIMESTAMP,
                                null,
                                ['nullable' => false, 'default' => \Magento\Framework\DB\Ddl\Table::TIMESTAMP_INIT_UPDATE],
                                'Updated At')
                        ->setComment('Omnivalt label history');
                $installer->getConnection()->createTable($table);
            }
        }
        
        if (version_compare($context->getVersion(), '1.2.3', '<')) {
            if ($installer->getConnection()->tableColumnExists('omnivalt_label_history', 'services') === false) {
                $installer->getConnection()->addColumn(
                        $installer->getTable('omnivalt_label_history'),
                        'services',
                        [
                            'type' => \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
                            'nullable' => true,
                            'comment' => 'List of services',
                            'after' => 'label_barcode'
                        ]
                );
            }
        }
        
        if(version_compare($context->getVersion(), '1.2.4', '<')) {

            $installer->getConnection()->addColumn(
                $installer->getTable('sales_order'),
                'omnivalt_services',
                [
                    'type' => \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
                    'length' => 255,
                    'nullable' => true,
                    'comment' => 'Omniva additional services',
                ]
            );
        }

        if(version_compare($context->getVersion(), '1.2.17', '<')) {
            $installer->getConnection()->changeColumn( 
                $setup->getTable('sales_order_address'), 
                'omnivalt_parcel_terminal',
                'omnivalt_parcel_terminal',
                [ 
                   'type' => \Magento\Framework\DB\Ddl\Table::TYPE_TEXT, 
                   'length' => 50, 
                   'nullable' => true, 
                   'comment' => 'Omnivalt Parcel Terminal' 
                ] 
            ); 
            $installer->getConnection()->changeColumn( 
                $setup->getTable('quote_address'), 
                'omnivalt_parcel_terminal',
                'omnivalt_parcel_terminal', 
                [ 
                   'type' => \Magento\Framework\DB\Ddl\Table::TYPE_TEXT, 
                   'length' => 50, 
                   'nullable' => true, 
                   'comment' => 'Omnivalt Parcel Terminal' 
                ] 
            ); 
        }

        $setup->endSetup();
    }

}
