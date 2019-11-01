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
     *         RelatedFaces::find()->asArray()->all(),
     *         'Связанные с банком лица',
     *         RelatedFaces::attributeLabels(),
     *         [20, 60, 60], //['EID_INN' => 20]
     *         ['EID_INN' => 'number']
     *     )
     * );
     * ```
     * 
     * @param array $data
     * @param string $title
     * @param array $col_titles Headings for each column to be used 
     * in the resulting xls file. You can use for this construct like 
     * array_values(YourModel::attributeLabels()).
     * @param int|array $col_width Width for each column to be used 
     * in the resulting xls file. If a number is passed, then is the same width 
     * columns will be used. 
     * @param array $format Associative array. Data formats for each column. 
     * The default format is 'general'. In current version only value 'number'
     * is supported.
     * 
     * return string Name of saved xlsx file with path
     */
    public static function saveAsXls($data, $title, $col_titles, $col_width = 20, $format = null)
    {
        // excel начало создание листа,
        $spreadsheet = new Spreadsheet();
        $username = Yii::$app->user->identity->username;
        $spreadsheet->getProperties()
                ->setTitle($title) // название файла
                ->setCreator($username) // автор файла
                ->setLastModifiedBy($username); // последний изменивший
        $activeSheet = $spreadsheet->getActiveSheet();
        // название листа (31 символ)
        $activeSheet->setTitle($title);
        $arrayData = array_values($data);
        //  начало основного архива в А2
        $sheet = $activeSheet->fromArray($arrayData, NULL, 'A2');
        
        
        $headArray = $col_titles; // названия колонок-заголовков 
        // берем заголовки из базы
        /*$rusfieldname = Yii::$app->db_v_sppr_riski_rep
                ->createCommand("select RUS_NAME from QUERIES_FIELD_NAME where BIZ_ACTION_SQL = '12' order by ID asc")
                ->queryAll();
        $rusfield = array_column($rusfieldname, 'RUS_NAME');*/

        // расчет границ основного массива: по оси Х $sizeX, по оси Y $sizeY
        $sizeX = count($headArray);
        $sizeY = count($data) + 1;
        
        $alphabet = range('A','Z');
        $columnNamesPossible = [];
        foreach ($alphabet as $char) {
            $columnNamesPossible[] = 'A' . $char;
        }
        $columnNamesPossible = array_merge($alphabet, $columnNamesPossible);

        $columnNames = array_slice($columnNamesPossible, 0 , $sizeX);
        $columnNameLast = $columnNames[count($columnNames) - 1];
        $columnNamesAssoc = array_combine(array_keys($col_titles), $columnNames);
        
        
        // выравнивание столбцов по установленным размерам ширины
        $col_width_default = 20;
        if (is_array($col_width)) {
            // is associative?
            if (count(array_filter(array_keys($col_width), 'is_string')) > 0) {
                $columnSize = array_fill(0, $sizeX, $col_width_default);
                foreach ($col_width as $key => $value) {
                    $columnSize[
                        array_search($columnNamesAssoc[$key], $columnNames)
                    ] = $value;
                }
            } else {
                $columnSize = $col_width;
            }
        } elseif (!empty($col_width)) {
            $columnSize = array_fill(0, $sizeX, $col_width);
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
        $activeSheet->freezePane('B2'); // зафиксировать заголовки (верхнюю строку до А2)

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
                    'argb' => 'FF23AAC7', // corporativen rncb
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
        
        /*// весь основной массив
        for ($x = 1; $x < $sizeX + 1; $x++) {
            for ($y = 2; $y < $sizeY + 2; $y++) {
                $sheet->getStyleByColumnAndRow($x, $y)->applyFromArray($styleArray);
            }
        } */
        
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
    }
}
