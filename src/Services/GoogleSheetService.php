<?php

namespace Zlt\LaravelGoogleSheet\Services;

use Google\Service\Sheets;
use Google\Service\Sheets\BatchUpdateSpreadsheetRequest;
use Google\Service\Sheets\CellFormat;
use Google\Service\Sheets\GridRange;
use Google\Service\Sheets\Request;
use Google\Service\Sheets\Spreadsheet;
use Google\Service\Sheets\ValueRange;

class GoogleSheetService
{
    protected ?Sheets $service = null;

    public function __construct(array|null $config = null)
    {
        $client = new \Google\Client();
        $config ??= config('filesystems.disks.google');
        $client->setScopes(Sheets::SPREADSHEETS);
        $client->setClientId($config['clientId']);
        $client->setClientSecret($config['clientSecret']);
        $client->refreshToken($config['refreshToken']);
        $client->setAccessType($config['type'] ?? 'online');
        $this->service = new Sheets($client);
    }

    public function getServiceInstance(): Sheets
    {
        return $this->service;
    }

    /**
     * @throws \Exception
     */
    public function insertValues(string $sheetId,
                                 string $range,
                                 array  $values,
                                 string $valueInputOption = "RAW"): \Google\Service\Sheets\AppendValuesResponse
    {
        $service = $this->service;
        if (empty($values)) throw new \Exception('Empty Values');
        $body = new ValueRange([
            'values' => $values
        ]);
        $params = [
            'valueInputOption' => $valueInputOption,
        ];
        return $service->spreadsheets_values->append($sheetId, $range, $body, $params);
    }

    public function updateValues(string $sheetId, string $sheetName, string $range, array $values, string $valueInputOption = "RAW")
    {
        $service = $this->service;
        if (empty($values)) throw new \Exception('Empty Values');
        $body = new ValueRange([
            'values' => $values
        ]);
        $params = [
            'valueInputOption' => $valueInputOption,
        ];
        $range = $sheetName . '!' . $range;
        return $service->spreadsheets_values->update($sheetId, $range, $body, $params);
    }

    public function getValuesBySheetName($sheetId, $sheetName, $range = ''): array
    {
        $service = $this->service;
        if (!$range) {
            $range = $sheetName;
        } else {
            $range = $sheetName . '!' . $range;
        }
        $response = $service->spreadsheets_values->get($sheetId, $range);
        return $response->getValues();
    }

    public function getSheetDetails(string $sheetId): \Google\Service\Sheets\Spreadsheet
    {
        $service = $this->service;
        return $service->spreadsheets->get($sheetId);
    }

    /**
     * @throws \Exception
     */
    public function get($sheetId, $range = ''): array
    {
        $service = $this->service;
        $response = $service->spreadsheets->get($sheetId);
        $sheets = $response->getSheets();
        if (count($sheets) > 0) {
            $temp = [];
            foreach ($sheets as $sheet) {
                $temp[] = $this->getValuesBySheetName($sheetId, $sheet->properties->title);
            }
            return array_merge(...$temp);
        }
        throw new \Exception('No Data found');
    }


    /**
     * @param  string  $sheetId
     * @param  array  $formatOptions
     * @param  array  $rows
     * @param  array  $columns
     * @return bool|Sheets\BatchUpdateSpreadsheetResponse
     * @throws \Exception
     */
    public function formatCells(string $sheetId, array $formatOptions, array $rows = [], array $columns = []): bool|Sheets\BatchUpdateSpreadsheetResponse
    {
        //[
        //     'textFormat' => [
        //         'fontSize' => 14,
        //         'bold' => true,
        //         'foregroundColor' => ['red' => 1.0] // Red color
        //     ],
        //     'numberFormat' => [
        //         'type' => 'CURRENCY',
        //         'pattern' => '$#,##0.00'
        //     ],
        //     'backgroundColor' => ['red' => 0.8, 'green' => 0.8, 'blue' => 0.8], // Light gray
        //     'horizontalAlignment' => 'CENTER',
        //     'wrapStrategy' => 'WRAP'
        // ]);
        $values = $this->get($sheetId);    

        $format = new CellFormat($formatOptions);

        $updateRequests = [];

        $maxRowIndex = count($values) - 1;

        foreach ($rows as $rowIndex) {
            $updateRange = new GridRange([
                'sheetId' => 0,
                'startRowIndex' => $rowIndex,
                'endRowIndex' => $rowIndex + 1, // Single row range
                'startColumnIndex' => 0,
                'endColumnIndex' => count($values[$rowIndex]) // All columns in the row
            ]);

            $updateRequest = new Request([
                'updateCells' => [
                    'range' => $updateRange,
                    'rows' => [
                        ['values' => array_map(function ($cell) use ($format) {
                            return ['userEnteredFormat' => $format];
                        }, $values[$rowIndex])]
                    ],
                    'fields' => 'userEnteredFormat.textFormat'
                ]
            ]);

            $updateRequests[] = $updateRequest;
        }

        foreach ($columns as $columnIndex) {
            $updateRange = new GridRange([
                'sheetId' => 0,
                'startRowIndex' => 0,
                'endRowIndex' => $maxRowIndex + 1,
                'startColumnIndex' => $columnIndex,
                'endColumnIndex' => $columnIndex + 1
            ]);

            $updateRequest = [
                'updateCells' => [
                    'range' => $updateRange,
                    'rows' => [],
                    'fields' => 'userEnteredFormat.textFormat'
                ]
            ];

            // Apply bold format to each row in the specified column
            foreach ($values as $rowIndex => $row) {
                if (isset($row[$columnIndex])) {
                    $updateRequest['updateCells']['rows'][$rowIndex] = [
                        'values' => [
                            ['userEnteredFormat' => $format]
                        ]
                    ];
                }
            }

            $updateRequests[] = new Request($updateRequest);
        }

        if(empty($updateRequests)){
            return false;
        }

        $batchUpdateRequest = new BatchUpdateSpreadsheetRequest([
            'requests' => $updateRequests
        ]);
        return $this->service->spreadsheets->batchUpdate($sheetId, $batchUpdateRequest);
    }

    /**
     * @param  string  $name
     * @param  array  $data
     * @return string
     * @throws \Exception
     */
    public function createSheet(string $name, array $data): string
    {
        $spreadsheet = new Spreadsheet([
            'properties' => [
                'title' => $name,
            ]
        ]);
        $spreadsheet = $this->service->spreadsheets->create($spreadsheet);
        $spreadsheetId = $spreadsheet->spreadsheetId;
        $startColumnLetter = 'A';
        $endColumnLetter = chr(ord($startColumnLetter) + count($data[0]) - 1);
        $startRow = 1;
        $endRow = count($data) - 1;

        $range = $startColumnLetter.$startRow.':'.$endColumnLetter.$endRow;
        $this->insertValues($spreadsheetId, $range, $data);
        return $spreadsheetId;
    }
}
