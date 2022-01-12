<?php

namespace Zlt\LaravelGoogleSheet\Services;

use Google_Service_Sheets;
use Google_Service_Sheets_ValueRange;

class GoogleSheetService
{
    private \Google\Service\Sheets $service;

    public function __construct(){
        $this->init();
    }

    private function init(){
        $client = new \Google\Client();
        $config = config('filesystems.disks.google');
        $client->setScopes(Google_Service_Sheets::SPREADSHEETS);
        $client->setClientId($config['clientId']);
        $client->setClientSecret($config['clientSecret']);
        $client->refreshToken($config['refreshToken']);
        $client->setAccessType('online');
        $this->service = new Google_Service_Sheets($client);
    }

    public function insertValues($spreadsheetId, $range,
                                 array $values,$valueInputOption = "RAW"): \Google\Service\Sheets\AppendValuesResponse
    {
        $service = $this->service;
        if(empty($values))throw \Exception('Empty Values');
        $body = new Google_Service_Sheets_ValueRange([
            'values' => $values
        ]);
        $params = [
            'valueInputOption' => $valueInputOption,
        ];
        return $service->spreadsheets_values->append($spreadsheetId, $range, $body, $params);
    }

    public function getValuesBySheetName($sheetId,$sheetName,$range=''):array
    {
        $service = $this->service;
        if(!$range){
            $range = $sheetName;
        }else{
            $range = $sheetName.'!'.$range;
        }
        $response = $service->spreadsheets_values->get($sheetId,$range);
        return $response->getValues();
    }

    public function getSheet(string $sheetId):\Google\Service\Sheets\Spreadsheet
    {
        $service = $this->service;
        return $service->spreadsheets->get($sheetId);
    }

    /**
     * @throws \Exception
     */
    public function get($sheetId, $range=''): array
    {
        $service = $this->service;
        $response = $service->spreadsheets->get($sheetId);
        $sheets = $response->getSheets();
        if(count($sheets)>0){
            $temp = [];
            foreach($sheets as $sheet){
                $temp[]=$this->getValuesBySheetName($sheetId,$sheet->properties->title);
            }
            return array_merge(...$temp);
        }
        throw new \Exception('No Data found');
    }
}
