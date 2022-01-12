## Google Sheet Service For Laravel

## Prerequisites

- PHP : `^7.4`

## Preparation

Insert the required credentials inside `config/filesystems.php` as follow.

```php
 'google' => [
            'clientId' => env('GOOGLE_DRIVE_CLIENT_ID'),
            'clientSecret' => env('GOOGLE_DRIVE_CLIENT_SECRET'),
            'refreshToken' => env('GOOGLE_DRIVE_REFRESH_TOKEN')
        ],
```

## General

- ### How to get **sheetId**
  You can get the sheetId from the url.
  For example, in the url **"https://docs.google.com/spreadsheets/d/1234567890/edit#gid=0"**,
  the sheetId would be `1234567890`.
    

## Available Methods

- **get($sheetId, $range='')**

    Get all rows inside the sheet.
    ```php
    $service = new Zlt\LaravelGoogleSheet\Services\GoogleSheetService();
    dd($service->get('sheetId'));
  
    // You can specify the range 
    dd($service->get('sheetId','A:G'))  
   ```

- **getValuesBySheetName($sheetId,$sheetName,$range='')**
 ```php
 $service = new Zlt\LaravelGoogleSheet\Services\GoogleSheetService();
 dd($service->getValuesBySheetName('sheetId','sheetName'));
 ```


- **getSheetDetails($sheetId)**
    
    Get details of sheet id.
```php
$service = new Zlt\LaravelGoogleSheet\Services\GoogleSheetService();
dd($service->getSheetDetails('sheetId'));
```

- **insertValues($sheetId, $range, array $values, $valueInputOption = "RAW")**
   Append new rows to sheet. 
```php
$service = new Zlt\LaravelGoogleSheet\Services\GoogleSheetService();
dd($service->insertValues('sheetId','A:D',[[1,2,3,4],[a,b,c,d]]));
```
