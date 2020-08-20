<?php

namespace app\helpers;

use Yii;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style;

/**
 * ArrayHelperExtended provides additional array functionality that you can use in your
 * application.
 *
 * @author Konstantin Zosimenko <pivasikkost@gmail.com>
 * @since 2.0
 */
class Array2Xls
{
    /**
     * Saves the transferred data array as a xlsx file
     * 
     * Usage example:
     * ```php
     * $this->redirect(
     *     Array2Xls::saveAsXls(
     *         SomeModel::find()->asArray()->all(),
     *         'Some title',
     *         SomeModel::attributeLabels(),
     *         [20, 60, 60], //['some_attribute' => 20]
     *         ['some_attribute' => 'number']
     *     )
     * );
     * ```
     * 
     * @param array $data
     * @param string $title
     * @param array $col_titles Headings for each column to be used 
     * in the resulting xls file. It should be an array of the form
     * ['some_attribute' => 'some_attribute_title', ...] .
     * Warning! Column order should be the same as in $data.
     * @param int|array $col_width Width for each column to be used 
     * in the resulting xls file. If a number is passed, then is the same width 
     * columns will be used. 
     * @param array $format Associative array. Data formats for each column. 
     * The default format is 'general'. In current version only value 'number'
     * is supported.
     * 
     * return string Name of saved xlsx file with path
     */
    public static function saveAsXls($data, $title, $col_titles = null, $col_width = 20, $format = null)
    {
        // excel начало создание листа,
        $spreadsheet = new Spreadsheet();
        $username = Yii::$app->user->identity->username;
        $spreadsheet->getProperties()
                ->setTitle($title) // название файла
                ->setCreator($username) // автор файла
                ->setLastModifiedBy($username); // последний изменивший
        $activeSheet = $spreadsheet->getActiveSheet();
        $activeSheet->setTitle(mb_substr($title, 0, 31)); // Максимум 31 символ разрешено использовать
        $arrayData = array_values($data);
        //  начало основного архива в А2
        $sheet = $activeSheet->fromArray($arrayData, NULL, 'A2');
        
        if (!isset($col_titles)) {
            if (isset($data[0])) {
                $col_titles = array_keys($data[0]);
                $col_titles = array_combine($col_titles, $col_titles);
            } else {
                $col_titles = [];
            }
        }
        
        $headArray = $col_titles; // названия колонок-заголовков 

        // расчет границ основного массива: по оси Х $sizeX, по оси Y $sizeY
        $sizeX = count($headArray);
        $sizeY = count($data) + 1;
        
        $alphabet = range('A','Z');
        $columnNamesPossible = [];
        foreach ($alphabet as $char) {
            $columnNamesPossible[] = 'A' . $char;
        }
        foreach ($alphabet as $char) {
            $columnNamesPossible[] = 'B' . $char;
        }
        $columnNamesPossible = array_merge($alphabet, $columnNamesPossible); // Max 78

        $columnNames = array_slice($columnNamesPossible, 0 , $sizeX); // [0 => 'A', 1 => 'B', ...]
        $columnNameLast = $columnNames[count($columnNames) - 1];
        $columnNamesAssoc = array_combine(array_keys($col_titles), $columnNames); // ['some_attribute' => 'A', ...]
        
        
        // выравнивание столбцов по установленным размерам ширины
        $col_width_default = 20;
        if (is_array($col_width)) {
            // is associative?
            if (count(array_filter(array_keys($col_width), 'is_string')) > 0) {
                $columnSize = array_fill(0, $sizeX, $col_width_default); // [ 0 => 20, 1 => 20, ...]
                foreach ($col_width as $key => $value) {
                    $index = array_search($columnNamesAssoc[$key], $columnNames);
                    $columnSize[$index] = $value;
                }
            } else {
                $columnSize = $col_width;
            }
        } elseif (!empty($col_width)) {
            $columnSize = array_fill(0, $sizeX, $col_width); // [ 0 => 20, 1 => 20, ...]
        }
        foreach ($columnNames as $index => $name) {
            if (!empty($columnSize[$index])) {
                $sheet->getColumnDimension($name)->setWidth($columnSize[$index]);
            } else {
                $sheet->getColumnDimension($name)->setWidth($col_width_default);
                //$sheet->getColumnDimension($name)->setAutoSize(true);
            }
        }
        
        $activeSheet->fromArray($headArray, NULL, 'A1'); //  начало заголовков в А1
        $activeSheet->setAutoFilter('A1:' . $columnNameLast . '1'); // установка авто фильтров
        $activeSheet->getRowDimension('1')->setRowHeight(40);	// установка высоты 1й строки заголовков 
        $activeSheet->getPageSetup()->setRowsToRepeatAtTopByStartAndEnd(1,1); // печать заголовков на каждой странице
        $activeSheet->freezePane('B2'); // зафиксировать строки выше и слева от ячейки B2 (т.е. строка 1 и столбец A)

        // архив для применения стилей и форматирования заголовков
        $styleHead = [
            'font' => [
                'bold' => true,
            ],
            'alignment' => [
                'horizontal' => Style\Alignment::HORIZONTAL_CENTER,
                'vertical' => Style\Alignment::VERTICAL_CENTER,
                'wrapText' => true, // обтекание текстом
            ],
            'borders' => [
                'allBorders' => [
                    // все границы толще
                    'borderStyle' => Style\Border::BORDER_THICK, 
                ],
            ],
            'fill' => [
                'fillType' => Style\Fill::FILL_SOLID,
                'color' => [
                    'argb' => 'FF23AAC7', // corporative color
                ],
            ],
        ];
        $activeSheet->getStyle('A1:' . $columnNameLast . '1')
                ->applyFromArray($styleHead);

        // архив для применения стилей и форматирования основного архива
        $styleArray = [
            'alignment' => [
                'horizontal' => Style\Alignment::HORIZONTAL_CENTER,
                'vertical' => Style\Alignment::VERTICAL_CENTER,
                'wrapText' => true, // обтекание текстом
            ],
            'borders' => [ // включить границы для основного архива
                'allBorders' => [
                    'borderStyle' => Style\Border::BORDER_THIN, // все границы тонким
                    'color' => [
                        'rgb' => '808080' // серый цвет границ основного архива
                        //'rgb' => '999999' // серый цвет границ основного архива
                    ],
                ],
            ],
        ];
        $activeSheet->getStyle('A2:' . $columnNameLast . $sizeY)
                ->applyFromArray($styleArray);
        
        // Set a specific cell format
        if (isset($format)) {
            foreach ($format as $attr => $value) {
                $col = $columnNamesAssoc[$attr]; //A, B, C, etc.
                if ($value === 'number') {
                    $activeSheet->getStyle($col . '1:' . $col . $sizeY)
                            ->getNumberFormat()
                            ->setFormatCode(Style\NumberFormat::FORMAT_NUMBER);
                }
            }
        }
        
        
        // сохраняем файл в папку web/xls
        $writer = new Xlsx($spreadsheet);
        $fullFileName = 'xls/Отчёт ' . $title .'.xlsx';
        $writer->save($fullFileName);
        
        return '/' . $fullFileName;
        
        /*// выводим файл в браузер
        header('Content-Type: application/vnd.ms-excel; charset=windows-1251;');
        header('Content-Disposition: attachment;filename="Some Name.xls"');
        header('Cache-Control: max-age=0');
        $writer->save('php://output');
        */
    }
}
