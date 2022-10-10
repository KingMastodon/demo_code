<?php

namespace frontend\models;

use yii\db\ActiveRecord;
use yii;


class DistantAnalystActivity extends ActiveRecord
{
    //тип имени значения которое записывается в БД
    const TYPE_PROP_VALUE_NAME_IS_TEXT = 0; //имя свойства записывается как лексическое (как есть)
    const TYPE_PROP_VALUE_NAME_IS_ID = 1;//имя свойства записывается как id

    const TYPE_PROP_LABEL_IS_TEXT = 0; //имя значения записывается как лексическое (как есть)
    const TYPE_PROP_LABEL_IS_ID = 1;//имя значения записывается как id

    const TYPE_DOCUMENT = 0;
    const TYPE_BANK_LOGO = 1;
    const TYPE_BANK_PROPERTIES = 2;
    const TYPE_BANK_PARAMS = 3;
    const TYPE_CREDIT_CONDITION_TABLE = 4;
    const TYPE_BANK_PARAM_INNER_DATA = 5;
    const TYPE_BANK_PROMO = 6;
    const TYPE_PROMO_RULE = 7;

    const ACTION_STATUS_UPLOADED = "Uploaded";
    const ACTION_STATUS_DOCUMENT_DELETED = "Deleted";

    const ACTION_STATUS_PARAM_VALUE_ADDED = "Значение добавлено";
    const ACTION_STATUS_PARAM_VALUE_REMOVED = "Значение удалено";

    const ACTION_STATUS_PROMO_PARAM_ADDED = "Промо параметр добавлен";
    const ACTION_STATUS_PROMO_PARAM_REMOVED = "Промо параметр Удален";

    const ACTION_STATUS_PARAM_CHANGED = "Изменено";
    const ACTION_STATUS_ENTITY_CREATED = "Сущность создана";
    const ACTION_STATUS_ENTITY_DELETED = "Сущность удалена";

    const SECTION_PROMO_PARAM = "Параметр акции";
    const SECTION_PROMO_PROP = "Свойство акции";

    const SECTION_PROMO_RULE_CONDITION = "Условие правила";
    const SECTION_PROMO_RULE_ACTION = "Действие павила";
    const SECTION_PROMO_RULE_TYPE = "Тип павила";

    const SECTION_PARAM_PROP = "Свойства параметра";
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'distant_analyst_activity';
    }


    public function rules()
    {
        return [
            [['type', 'bank_id', 'user_id', 'created_at', 'prop_value_name_type', 'host_id'], 'required'],
            [['type', 'bank_id', 'user_id', 'created_at', 'prop_value_name_type', 'host_id'], 'integer'],
            [['prop_name', 'prop_status', 'prop_value_before', 'prop_value_after', 'prop_comment', 'section'], 'string'],
        ];
    }

    public function attributeLabels()
    {
        return [
            'id' => 'Id',
            'type' => 'Тип',
            'user_id' => 'Аналитик',
            'bank_id' => 'Банк',
            'host_id' => 'id сущности',
            'section' => 'Раздел', //чья модель, напр акции, если банка там нет
            'bank_region_id' => 'Регион банка',
            'created_at' => 'Дата',
            'prop_name' => 'Имя',
            'prop_status' => 'Статус',
            'prop_value_before' => 'Значение до',
            'prop_value_after' => 'Значение после',
            'prop_value_name_type' => 'Тип имени значения',
            'prop_label_type' => 'Тип имени свойства',
            'prop_comment' => 'Комментарий',
        ];
    }

    public static function logAnalystActivity(
        int    $type,
        ?Banks  $bank,
        ?string $section,
        string  $propName,
        string $bankPropStatus,
        array  $propValueBefore,
        array  $propValueAfter,
        int $propValueNameType,
        int $propLabelType,
        string $bankPropComment,
        ?int $hostId
    )
    {
        if (count($propValueBefore) > 0 || count($propValueAfter) > 0) {
            $user = Yii::$app->user;
            $distantAnalystActivity = new self;
            $distantAnalystActivity->type = $type;
            $distantAnalystActivity->bank_id = isset($bank) ? $bank->id : 0;
            $distantAnalystActivity->user_id = $user->id;
            $distantAnalystActivity->created_at = time();
            $distantAnalystActivity->prop_name = $propName;
            $distantAnalystActivity->prop_status = $bankPropStatus;
            $distantAnalystActivity->prop_value_before = serialize($propValueBefore);
            $distantAnalystActivity->prop_value_after = serialize($propValueAfter);
            $distantAnalystActivity->prop_comment = $bankPropComment;
            $distantAnalystActivity->prop_value_name_type = $propValueNameType;
            $distantAnalystActivity->prop_label_type = $propLabelType;
            $distantAnalystActivity->bank_region_id = isset($bank) ? $bank->region : null;
            $distantAnalystActivity->section = $section;
            $distantAnalystActivity->host_id = $hostId;

            if ($distantAnalystActivity->validate()) {
                $distantAnalystActivity->save();
            }
        }
    }

    public static function convertVariousValuesIntoNames(array $array): array
    {
        $result = [];
        foreach ($array as $element) {
            if (isset ($element['minvalue'])) {
                $result[] = 'min: ' . $element['minvalue'];
            }
            if (isset ($element['maxvalue'])) {
                $result[] = 'max: ' . $element['maxvalue'];
            }
            if (isset ($element['value'])) {
                $result[] = $element['value'];
            }
            if (isset ($element['value_text'])) {
                $result[] = $element['value_text'];
            }
        }
        return $result;
    }

    /**
     * @param array $array
     * @return array
     * устанавливает единые типы значениям параметров перед сравнением объектов
     */
    public static function setTypesOfValues(array $array): array
    {
        return array_map(function ($items) {
            $items['minvalue'] = (float)$items['minvalue'];
            $items['maxvalue'] = (float)$items['maxvalue'];
            $items['value'] = $items['value'] ?? null;
            $items['value_text'] = $items['value_text'] ?? null;
            return $items;
        },
            $array);
    }

    /**
     * @param array $array
     * @return array
     * Чистит "призрачные параметры"
     */
    public static function clearBuggedParams(array $array): array
    {
        foreach ($array as $key => $items) {
            if (empty($items)) {
                unset($array[$key]);
            }
        }
        return $array;
    }

    public static function getTypes():array
    {
        return self::find()->select('type')->distinct()->column();
    }

    public static function getBanks():array
    {
        return self::find()->select('bank_id')->distinct()->column();
    }

    public static function getUserIds():array
    {
        return self::find()->select('user_id')->distinct()->column();
    }
}