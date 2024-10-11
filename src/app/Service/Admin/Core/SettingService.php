<?php

namespace App\Service\Admin\Core;

use App\Enums\StatusEnum;
use App\Models\PricingPlan;
use Illuminate\Support\Arr;
use App\Models\Setting;
use App\Rules\FileExtentionCheckRule;
use App\Service\Admin\Core\FileService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;

class SettingService {

    public function getIndex($type) {

        $data = [

            "title" => translate(ucfirst($type)." Settings"),
        ];
        switch($type) {

            case "general":

                $type_data = [
                    "countries"     => json_decode(file_get_contents(resource_path('views/partials/country_file.json'))),
                    "timeLocations" => collect(timezone_identifiers_list())->groupBy(function($item) {
                        return explode('/', $item)[0];
                    })
                ];
                $data = array_merge($data, $type_data);
                break;

            case "member" :

                $type_data = [
                    "plans" => PricingPlan::select('id', 'name')->latest()->get(),
                ];
                $data = array_merge($data, $type_data);
                break;
            case "authentication":

                $data["title"] = translate("Authentication Page Setup");
        }

        return $data;
    }
    
    /**
     * settings validations
     * 
     * @return array
     */
    public function validationRules(array $request_data ,string $key = 'site_settings') :array{

        $rules      = [];
        $message    = [];

        foreach ($request_data as $data_key => $data_value) {

            if ($data_value instanceof UploadedFile) {

                $rules[$key . "." . $data_key] = ['nullable', 'image', new FileExtentionCheckRule(json_decode(site_settings('mime_types'), true))];
            } else {
                
                $rules[$key . "." . $data_key] = ['nullable'];
                $messages[$key . "." . $data_key . '.nullable'] = ucfirst(str_replace('_', ' ', $data_key)) . ' ' . translate('Field is Required');
            }
        }
        return [
            'rules'   => $rules,
            'message' => $message
        ];
    }

    /**
     * update  settings
     * 
     * @param array $request_data
     */
    public function updateSettings(array $request_data): void {
        
        $json_keys = Arr::get(config('setting'), 'json_object', []);
        $fileService = new FileService();
        foreach ($request_data as $key => $value) {
            
            if ($value instanceof UploadedFile) {
                
                $filePath = $fileService->uploadFile($value, $key);
                
                if ($filePath) {
                    
                    $value = $filePath;
                }
            } elseif (in_array($key, $json_keys)) {
                
                $value = json_encode($value);
            }
            try {
                
                Setting::updateOrInsert(
                    ['key'   => $key],
                    ['value' => $value]
                );
                
                Cache::forget("site_settings");
            } catch (\Throwable $th) { }
        }
    }

    public function prepData($request) {

        $is_default = null;
        $data['currencies'] = json_decode(site_settings("currencies"), true);
        if($request->has('old_code')) {
            
            $is_default = $data['currencies'][$request->input('old_code')]['is_default'];
            unset($data['currencies'][$request->input('old_code')]);
        }
        $data['currencies'][$request->input('code')] = [

            'name'       => $request->input('name'),
            'symbol'     => $request->input('symbol'),
            'rate'       => $request->input('rate'),
            'status'     => StatusEnum::TRUE->status(),
            'is_default' => $is_default == StatusEnum::TRUE->status() ? StatusEnum::TRUE->status() : StatusEnum::FALSE->status(),
        ];
        return $data;
    }

    public function statusUpdate($request) {

        $status  = true;
        $reload  = false;
        $column   = $request->input("column");
        $message  = $column != "is_default" ? translate('Currency status updated successfully') : translate("Default currency changed");
        $data['currencies'] = json_decode(site_settings('currencies'), true);
        
        if ($column != 'is_default' && $data['currencies'][$request->input('id')]['status'] == StatusEnum::TRUE->status() &&  $data['currencies'][$request->input('id')]['is_default'] != StatusEnum::TRUE->status()) {

            $data['currencies'][$request->input('id')]['status'] = StatusEnum::FALSE->status();
            
        } elseif ($column != 'is_default' && $data['currencies'][$request->input('id')]['status'] == StatusEnum::FALSE->status()) {

           $data['currencies'][$request->input('id')]['status'] = StatusEnum::TRUE->status();

        } elseif($column == 'is_default') {

            
            $reload = true;
            $data['currencies'] = array_map(function ($currency) {
                $currency['is_default'] = StatusEnum::FALSE->status();
                return $currency;
            }, $data['currencies']);
            $data['currencies'][$request->input('id')]['is_default'] = StatusEnum::TRUE->status();
            $data['currencies'][$request->input('id')]['status'] = StatusEnum::TRUE->status();

        } else {

            $status  = false;
            $reload  = true;
            $message = translate("Can not disable default currency status");
        }

        return [
            $status, 
            $reload, 
            $message,
            $data
        ];
    }
}
