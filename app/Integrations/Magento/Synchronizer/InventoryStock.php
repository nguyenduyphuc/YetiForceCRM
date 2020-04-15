<?php

/**
 * Synchronize inventory stock file.
 *
 * @package Integration
 *
 * @copyright YetiForce Sp. z o.o
 * @license   YetiForce Public License 3.0 (licenses/LicenseEN.txt or yetiforce.com)
 * @author    Mariusz Krzaczkowski <m.krzaczkowski@yetiforce.com>
 */

namespace App\Integrations\Magento\Synchronizer;

/**
 * Synchronize inventory stock class.
 */
class InventoryStock extends Base
{
	/**
	 * Storage id.
	 *
	 * @var int
	 */
	public $storageId;
	/**
	 * Products ids.
	 *
	 * @var int[]
	 */
	public $products;

	/**
	 * {@inheritdoc}
	 */
	public function process()
	{
		$products = [];
		if ('Products' === $this->config->get('storage_quantity_location')) {
			$products = $this->getStockFromProducts();
		} elseif ('IStorages' === $this->config->get('storage_quantity_location') && ((int) $this->config->get('storage_id') === $this->storageId)) {
			$products = $this->getStockFromStorage();
		}
		foreach ($products as $product) {
			try {
				\App\Log::beginProfile('GET|stockItems|' . $product['ean'], 'Integrations/MagentoApi');
				$data = \App\Json::decode($this->connector->request('GET', $this->config->get('store_code') . '/V1/stockItems/' . $product['ean']));
				\App\Log::endProfile('GET|stockItems|' . $product['ean'], 'Integrations/MagentoApi');

				$data['qty'] = $product['qtyinstock'];

				\App\Log::beginProfile('PUT|stockItems|' . $product['ean'], 'Integrations/MagentoApi');
				$this->connector->request('PUT', "{$this->config->get('store_code')}/V1/products/{$product['ean']}/stockItems/{$data['item_id']}", ['stockItem' => $data]);
				\App\Log::endProfile('PUT|stockItems|' . $product['ean'], 'Integrations/MagentoApi');
			} catch (\Throwable $ex) {
				\App\Log::error('Error during update stock: ' . PHP_EOL . $ex->__toString() . PHP_EOL, 'Integrations/Magento');
			}
		}
	}

	/**
	 * Get stock from products.
	 *
	 * @return array
	 */
	public function getStockFromProducts()
	{
		$queryGenerator = new \App\QueryGenerator('Products');
		$queryGenerator->setStateCondition('All');
		$queryGenerator->setFields(['id', 'qtyinstock', 'ean'])->permissions = false;
		$queryGenerator->addCondition('id', $this->products, 'e');
		return $queryGenerator->createQuery()->all();
	}

	/**
	 * Get stock from storage.
	 *
	 * @return array
	 */
	public function getStockFromStorage()
	{
		$referenceInfo = \Vtiger_Relation_Model::getReferenceTableInfo('Products', 'IStorages');
		return(new \App\Db\Query())->select([
			'id' => $referenceInfo['table'] . '.' . $referenceInfo['rel'],
			'qtyinstock' => $referenceInfo['table'] . '.qtyinstock',
			'ean' => 'vtiger_products.ean'])
			->from($referenceInfo['table'])
			->innerJoin('vtiger_products', "{$referenceInfo['table']}.{$referenceInfo['rel']} = vtiger_products.productid")
			->where([$referenceInfo['base'] => $this->storageId, $referenceInfo['rel'] => $this->products])
			->all();
	}
}
