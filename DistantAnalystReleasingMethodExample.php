<?php

//Сюда записываются два состояния модели, значения до измененений и после. После чего состояния сравниваются
public static function logBankParamModelDiffInAnalystAction(array $valuesBefore = [], array $valuesAfter = [], ?int $bankId, BankParam $thisBankParam, int $type, ?string $section = null)
{
	$banks = isset($bankId) ? Banks::findOne(['id' => $bankId]) : null;
	//удаляем таймштампы, чтобы система не записала в базу по признаку различия до и после
	$valuesBefore = array_map(
		function ($valueObject) {
			ArrayHelper::remove($valueObject, 'updated_at');
			ArrayHelper::remove($valueObject, 'status_date');
			return $valueObject;
		},
		$valuesBefore);

	$valuesAfter = array_map(
		function ($valueObject) {
			unset($valueObject['updated_at']);
			unset($valueObject['status_date']);
			return $valueObject;
		},
		$valuesAfter);


	if ($thisBankParam->type_id == BankParam::TYPE_LIST_MULTIPLE) {

		$resultMultiParamValuesAddedByAnalyst = array_diff(array_keys($valuesAfter), array_keys($valuesBefore));
		$resultMultiParamValuesRemovedByAnalyst = array_diff(array_keys($valuesBefore), array_keys($valuesAfter));
		$resultMultiParamValuesAddedByAnalyst = DistantAnalystActivity::clearBuggedParams($resultMultiParamValuesAddedByAnalyst);
		$resultMultiParamValuesRemovedByAnalyst = DistantAnalystActivity::clearBuggedParams($resultMultiParamValuesRemovedByAnalyst);
		if (count($resultMultiParamValuesAddedByAnalyst) > 0) {
			$resultAfter = $resultMultiParamValuesAddedByAnalyst;
			foreach ($resultAfter as $valueId) {
				DistantAnalystActivity::logAnalystActivity(
					$type,
					$banks,
					$section,
					$thisBankParam->name,
					DistantAnalystActivity::ACTION_STATUS_PARAM_VALUE_ADDED,
					[],
					[$valuesAfter[$valueId]['value']],
					DistantAnalystActivity::TYPE_PROP_VALUE_NAME_IS_ID,
					DistantAnalystActivity::TYPE_PROP_LABEL_IS_TEXT,
					'',
					$bankId
				);
			}
		}

		if (count($resultMultiParamValuesRemovedByAnalyst) > 0) {
			#$resultBefore =self::getParamValueName($resultMultiParamValuesRemovedByAnalyst, $valuesBefore);
			$resultBefore = $resultMultiParamValuesRemovedByAnalyst;
			foreach ($resultBefore as $valueId) {
				DistantAnalystActivity::logAnalystActivity(
					$type,
					$banks,
					$section,
					$thisBankParam->name,
					DistantAnalystActivity::ACTION_STATUS_PARAM_VALUE_REMOVED,
					[$valuesBefore[$valueId]['value']],
					[],
					DistantAnalystActivity::TYPE_PROP_VALUE_NAME_IS_ID,
					DistantAnalystActivity::TYPE_PROP_LABEL_IS_TEXT,
					'',
					$bankId
				);
			}
		}
	} else {
		//парметер не мульти

		//находим параметры которые были изменены
		$valuesBefore = DistantAnalystActivity::setTypesOfValues($valuesBefore);
		$valuesAfter = DistantAnalystActivity::setTypesOfValues($valuesAfter);
		//определяем что изменилось в модели, и записываем затронутые свойства в базу
		$reducedObjectBefore = MiscHelper::twoDimmArrayDiffSimple($valuesBefore, $valuesAfter);
		$reducedObjectAfter = MiscHelper::twoDimmArrayDiffSimple($valuesAfter, $valuesBefore);
		if (!empty($reducedObjectBefore) || !empty($reducedObjectAfter)) {
			if (
				$thisBankParam->type_id == BankParam::TYPE_LIST || $thisBankParam->type_id == BankParam::TYPE_LIST_ASC ||
				$thisBankParam->type_id == BankParam::TYPE_LIST_DESC
			) {
				//значения параметров являются id и связаны с таксономией
				$reducedObjectBefore = [$reducedObjectBefore[array_key_first($reducedObjectBefore)]['value']];
				$reducedObjectAfter = [$reducedObjectAfter[array_key_first($reducedObjectAfter)]['value']];
				$propValueNameType = DistantAnalystActivity::TYPE_PROP_VALUE_NAME_IS_ID;
				$propLabelType = DistantAnalystActivity::TYPE_PROP_LABEL_IS_TEXT;
			} else {
				//просто запмсываем значение в журнал, за исключением min и max- их обрабатываем
				$reducedObjectBefore = DistantAnalystActivity::convertVariousValuesIntoNames($reducedObjectBefore);
				$reducedObjectAfter = DistantAnalystActivity::convertVariousValuesIntoNames($reducedObjectAfter);
				$propValueNameType = DistantAnalystActivity::TYPE_PROP_VALUE_NAME_IS_TEXT;
				$propLabelType = DistantAnalystActivity::TYPE_PROP_LABEL_IS_TEXT;
			}

			DistantAnalystActivity::logAnalystActivity(
				$type,
				$banks,
				$section,
				$thisBankParam->name,
				DistantAnalystActivity::ACTION_STATUS_PARAM_CHANGED,
				$reducedObjectBefore,
				$reducedObjectAfter,
				$propValueNameType,
				$propLabelType,
				'',
				$bankId
			);
		}
	}
}
