<?php

namespace app\helpers;

/**
 * Implements additional common frequently used functions for working with arrays, 
 * not implemented in the standard php and yii\helpers\ArrayHelper
 *
 * @author Konstantin Zosimenko <pivasikkost@gmail.com>
 * @since 2.0
 */
class CustomArrayHelper
{
    /**
     * Returns a specified columns in an array.
     * The input array should be multidimensional or an array of objects.
     *
     * For example,
     *
     * ```php
     * $array = [
     *     ['id' => '123', 'data' => 'abc', 'data2' => 'abc2'],
     *     ['id' => '345', 'data' => 'def', 'data2' => 'def2'],
     * ];
     * $result = CustomArrayHelper::getColumns($array, ['id', 'data2']);
     * // the result is: 
     *      [
     *          ['id' => '123', 'data2' => 'abc2'] , 
     *          ['id' => '345', 'data2' => 'def2']
     *      ]
     *
     * @param array $array
     * @param string[] $columnKeys
     * @return array with specified columns
     */
    public static function getColumns(array $array, array $columnKeys): array
    {
        $resultArray = [];
        foreach ($array as $value) {
            $resultArray[] = array_intersect_key($value, array_flip($columnKeys));
        }
        unset($value);
        
        return $resultArray;
    }
    
    /**
     * Returns an array without specified columns.
     * The input array should be multidimensional
     * 
     * @link https://stackoverflow.com/questions/16564650/best-way-to-delete-column-from-multidimensional-array
     * For example,
     *
     * ```php
     * $array = [
     *     ['id' => '123', 'data' => 'abc', 'data2' => 'abc2'],
     *     ['id' => '345', 'data' => 'def', 'data2' => 'def2'],
     * ];
     * $result = CustomArrayHelper::deleteColumns($array, ['id', 'data2']);
     * // the result is: 
     *      [
     *          ['data' => 'abc'] , 
     *          ['data' => 'def']
     *      ]
     *
     * @param array $array
     * @param string[] $columnKeys
     * @return bool <b>TRUE</b> on success or <b>FALSE</b> on failure.
     */
    public static function deleteColumns(array &$array, array $columnKeys): bool
    {
        return array_walk(
                $array,
                function (&$value) use ($columnKeys) {
                    foreach ($columnKeys as $key) {
                        unset($value[$key]);
                    }
                }
        );
    }
}
