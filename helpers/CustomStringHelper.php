<?php

namespace app\helpers;

//use yii\helpers\StringHelper;

/**
 * Implements additional common frequently used functions for working with strings, 
 * not implemented in the standard php and yii\helpers\StringHelper
 *
 * @author Konstantin Zosimenko <pivasikkost@gmail.com>
 * @since 2.0
 */
class CustomStringHelper
{
    /**
     * Rounds a float and add leading zero
     *
     * For example,
     *
     * ```php
     * $result = CustomStringHelper::addLeadingZeroAndRound(".00", 8);
     * // the result is: 
     *      0
     * $result = CustomStringHelper::addLeadingZeroAndRound(",540", 6);
     * // the result is: 
     *      0.54
     * $result = CustomStringHelper::addLeadingZeroAndRound(".55", 1, ',');
     * // the result is: 
     *      0,6
     *
     * @param string|float $number
     * @param int $precision
     * @param string $resSeparator The decimal separator for result ('.' or ',')
     * @return string with specified columns
     */
    public static function addLeadingZeroAndRound($number, int $precision = 2, string $resSeparator = '.'): string
    {
        // . and , are the only decimal separators known in ICU data,
        //$number = StringHelper::normalizeNumber($number);
        $number = str_replace(',', '.', (string) $number);
        
        $number =  rtrim(
            rtrim(
                sprintf("%." . $precision . "f",$number), //spritf("%.2f", $number)
                '0'
            ), 
            '.'
        ); 
        
        if ($resSeparator !== null && $resSeparator !== '.') {
            $number = str_replace('.', $resSeparator, (string) $number);
        }
        
        return $number;
    }
}
