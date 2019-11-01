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
     * Creates data provider instance with search query applied
     *
     * @param array $params
     * @return ArrayDataProvider
     */
    public function search($params)
    {
        $query = parent::find()->asArray();
        $allModels = $this->getAllModels($query);
        
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
        
        if ($from_cache) {
            return $cache->getOrSet($key, function () use ($query) {
                return $query->all();
            }, $duration);
        } else {
            $cache->delete($key);
            $allModels = $query->all();
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
                if(array_key_exists($key, $row) && trim($value) !== '') {
                    if (static::getTableSchema()->columns[$key]->dbType == 'NUMBER'
                        || isset(static::$strict_comparison[$key])
                    ) {
                        if ($row[$key] != $value) {
                            unset($data[$rowIndex]);
                        }
                    } elseif (mb_stripos($row[$key], $value) === false) {
                        unset($data[$rowIndex]);
                    }
                }
            }
        }
        
        return $data;
    }
}

