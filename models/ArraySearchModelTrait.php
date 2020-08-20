<?php

namespace app\models\system;

use Yii;
use yii\data\ArrayDataProvider;

/**
 * ArraySearchModelTrait provides a general implementation of search model methods
 * when working with sql views or ready-made large and complex sql queries.
 * When using standard gii-generated methods of search models is not possible 
 * due to slow speed or sql filtering.
 * 
 * When using, you need to remove the search() method from the generated gii model
 * (since it has a higher priority)
 *
 * @author Konstantin Zosimenko <pivasikkost@gmail.com>
 * @since 2.0
 */
trait ArraySearchModelTrait
{
    /**
     * (traits cannot contain static properties, but you can set them in your class)
     */
    
    /**
     * @var array The columns to be selected from the table in the desired order
     */
    //public static $columns_for_select = ['ASOURCE', 'TYPE_PRODUCT'];
    
    /**
     * @var array Attribute names for which strict comparison should be applied 
     * when filtering in ArraySearchModelTrait filter() method
     */
    //public static $strict_comparison = ['ASOURCE' => true, 'TYPE_PRODUCT' => true];
    
    /**
     * @var bool Flag whether leading zeros should be added to numerical values
     */
    //public static $add_leading_zeros = true;
    
    /**
     * @var int The time, in seconds, for which you need to cache data
     * in the ArraySearchModelTrait getAllData() method
     */
    //public static $cache_duration = 600; //10min
    
    /**
     * Creates data provider instance with search query applied
     *
     * @param array $params
     * @return ArrayDataProvider
     */
    public function search($params, $from_cache = null)
    {
        $query = parent::find()->asArray();
        $allModels = $this->getAllModels($query, $from_cache);
        
        $this->load($params);
        if ($this->validate()) {
            // grid filtering conditions
            $allModels = $this->filter($allModels);
        }
        
        $dataProvider = new ArrayDataProvider([
            'allModels' => $allModels,
            'sort' => [
                'attributes' => $this->attributes(),
            ]
        ]);

        return $dataProvider;
    }
    
    /**
     * Caches sql query results. 
     * By default if http request is sent using ajax (sorting, filtering, pagination)
     * 
     * @param ActiveQuery $query
     * @param bool $from_cache
     * @return ActiveRecord|array
     */
    public function getAllModels($query, $from_cache = null)
    {
        $cache = Yii::$app->cache;
        $key = [static::class, 'allModels']; //unique key for cache value
        $duration = static::$cacheDuration ?? 600; //10min
        $from_cache = $from_cache ?? Yii::$app->request->isAjax;
        
        if (isset(static::$columns_for_select)) {
            $query->select(static::$columns_for_select);
        }
        
        if ($from_cache) {
            return $cache->getOrSet($key, function () use ($query) {
                if (!empty(static::$add_leading_zeros)) {
                    return static::addLeadingZeros($query->all());
                } else {
                    return $query->all();
                }
            }, $duration);
        } else {
            $cache->delete($key);
            if (!empty(static::$add_leading_zeros)) {
                $allModels = static::addLeadingZeros($query->all());
            } else {
                $allModels = $query->all();
            }
            $cache->set($key, $allModels, $duration);
            return $allModels;
        }
    }
    
    /**
     * Filter input array by key value pairs
     * @param array $data rawData
     * @return array filtered data array
     * @link https://yiiframework.ru/forum/viewtopic.php?t=21163
     */
    public function filter(array $data)
    {
        foreach($data as $rowIndex => $row) {
            foreach($this->getAttributes() as $key => $value) {
                // unset if filter is set, but doesn't match
                if (array_key_exists($key, $row)) {
                    if (is_array($value)) { //multiselect|checkbox
                        $unset = true;
                        foreach ($value as $value_item) {
                            if ($row[$key] === $value_item) {
                                $unset = false;
                                break;
                            }
                        }
                        if ($unset) {
                            unset($data[$rowIndex]);
                        }
                    } elseif (trim($value) !== '') { //other inputs
                        if (static::getTableSchema()->columns[$key]->dbType == 'NUMBER') { // '.' = ','
                            if (CustomStringHelper::addLeadingZeroAndRound($row[$key]) !== CustomStringHelper::addLeadingZeroAndRound($value)) {
                                unset($data[$rowIndex]);
                            }
                        } elseif (isset(static::$strict_comparison[$key])) {
                            if ($row[$key] !== $value) {
                                unset($data[$rowIndex]);
                            }
                        } elseif (mb_stripos($row[$key], $value) === false) {
                            unset($data[$rowIndex]);
                        }
                    }
                }
            }
        }
        
        return $data;
    }
    
    /**
     * Rounds numerical values and adds leading zeros
     *
     * @param array $data Values without leading zeros
     * @return array Values with leading zeros
     */
    public function addLeadingZeros(array $data): array
    {
        foreach($data as &$row) {
            foreach(static::getTableSchema()->columns as $key => $value) {
                if (array_search($key, static::$columns_for_select) !== false
                    && $value->dbType === 'NUMBER'
                ) {
                    $row[$key] = CustomStringHelper::addLeadingZeroAndRound($row[$key], 9, ',');
                }
            }
        }
        unset($row);
        
        return $data;
    }
}

