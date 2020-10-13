<?php
/**
 * Base fields dependency handler file.
 *
 * @package		Handler
 *
 * @copyright	YetiForce Sp. z o.o
 * @license		YetiForce Public License 3.0 (licenses/LicenseEN.txt or yetiforce.com)
 * @author		Mariusz Krzaczkowski <m.krzaczkowski@yetiforce.com>
 */
/**
 * Base fields dependency handler class.
 */
class Vtiger_FieldsDependency_Handler
{
	/**
	 * EditViewChangeValue handler function.
	 *
	 * @param App\EventHandler $eventHandler
	 */
	public function editViewChangeValue(App\EventHandler $eventHandler)
	{
		$return = [];
		$recordModel = $eventHandler->getRecordModel();
		$fieldsDependency = \App\FieldsDependency::getByRecordModel(\App\Request::_getByType('fromView'), $recordModel);
		if ($fieldsDependency['show']['frontend']) {
			$return['showFields'] = $fieldsDependency['show']['frontend'];
		}
		if ($fieldsDependency['hide']['frontend']) {
			$return['hideFields'] = $fieldsDependency['hide']['frontend'];
		}
		return $return;
	}

	/**
	 * EditViewPreSave handler function.
	 *
	 * @param App\EventHandler $eventHandler
	 */
	public function editViewPreSave(App\EventHandler $eventHandler)
	{
		$recordModel = $eventHandler->getRecordModel();
		$response = ['result' => false];
		$fieldsDependency = \App\FieldsDependency::getByRecordModel(\App\Request::_getByType('fromView'), $recordModel);
		if ($fieldsDependency['show']['mandatory']) {
			$mandatoryFields = [];
			foreach ($fieldsDependency['show']['mandatory'] as $fieldName) {
				if ('' === $recordModel->get($fieldName)) {
					$mandatoryFields[] = $recordModel->getField($fieldName)->getFullLabelTranslation();
				}
			}
			if ($mandatoryFields) {
				$response = [
					'result' => false,
					'hoverField' => reset($fieldsDependency['show']['mandatory']),
					'message' => \App\Language::translate('LBL_NOT_FILLED_MANDATORY_FIELDS') . ': <br /> - ' . implode('<br /> - ', $mandatoryFields)
				];
			}
		}
		return $response;
	}

	/**
	 * Get variables for the current event.
	 *
	 * @param string $name
	 * @param array  $params
	 *
	 * @return array|null
	 */
	public function vars(string $name, array $params): ?array
	{
		if (\App\EventHandler::EDIT_VIEW_CHANGE_VALUE === $name) {
			[$recordModel,$view] = $params;
			return \App\FieldsDependency::getByRecordModel($view, $recordModel)['conditionsFields'];
		}
		return null;
	}
}
