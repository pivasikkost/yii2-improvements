<?php

namespace app\helpers;

/**
 * Array2Csv provides additional array functionality that you can use in your
 * application.
 *
 * @author Konstantin Zosimenko <pivasikkost@gmail.com>
 * @since 2.0
 */
class Array2Csv
{
    /**
     * Saves the transferred data array as a csv file
     * 
     * Usage example:
     * ```php
     * $array = [
     *      ['col1' => 1, 'col2' => 2, 'col3' => 3], 
     *      ['col1' => 4, 'col2' => 5, 'col3' => 6],
     *      ['col1' => 7, 'col2' => 8, 'col3' => 9],
     * ];
     * $col_titles = ['col1' => 'столбец 1', 'col2' => 'столбец 2', 'col3' => 'столбец 3'];
     * $col_delimiter = "|";
     * $row_delimiter = "\r\n";
     * 
     * $content = Array2Csv::convertToCsv($array, $col_titles, $col_delimiter, $row_delimiter);
     * //$content = mb_convert_encoding($content, 'Windows-1251', 'UTF-8'); //may cause problems with auto-detection
     * Yii::$app->response->sendContentAsFile(
     *      $content, 
     *      'my_file_' . date('d.m.Y') . '.csv'
     * )->send();
     * ```
     * 
     * @param array $data
     * @param string[] $col_titles Headings for each column to be used 
     * in the resulting csv file. You can use for this construct like 
     * array_values(YourModel::attributeLabels()).
     * @param string $col_delimiter
     * @param string $row_delimiter like Unix "\n" or Windows "\r\n"
     * 
     * return string Converted data as text
     */
    public static function convertToCsv(
        array $data, 
        array $col_titles = null, 
        string $col_delimiter = "|", 
        string $row_delimiter = "\r\n"
    ): string {
        if (!isset($col_titles)){
            $col_titles = isset($data[0]) ? array_keys($data[0]) : [];
        }
        
        $rows = [];
        $rows[] = implode($col_delimiter, $col_titles);
        foreach ($data as $item) {
            $row = implode($col_delimiter, $item);
            $rows[] = str_replace(["\r\n", "\r", "\n"], ' ', $row); // Исправляем баг, связанный с  переносами строк в столбце 
        }

        return implode($row_delimiter, $rows);
    }
    

//        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Csv($spreadsheet);
//        $writer->setDelimiter(';');
//        $writer->setEnclosure('');
//        $writer->setLineEnding("\r\n");
//        $writer->setSheetIndex(0);
//
//        $writer->save("my_file.csv");
    
}
