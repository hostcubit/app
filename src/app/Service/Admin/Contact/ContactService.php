<?php

namespace App\Service\Admin\Contact;

use App\Models\User;
use App\Models\Group;
use App\Models\Import;
use App\Models\Contact;
use App\Jobs\ImportJob;
use App\Enums\StatusEnum;
use App\Imports\ContactImport;
use Illuminate\Support\Facades\File;
use Maatwebsite\Excel\Facades\Excel;

class ContactService
{ 
    public function getAllContacts($user_id = null) {

        return Contact::search(['first_name|last_name', 'first_name', 'last_name', 'whatsapp_contact', 'email_contact', 'sms_contact'])
                        ->where("user_id", $user_id)
                        ->filter(['status'])
                        ->latest()
                        ->date()
                        ->paginate(paginateNumber(site_settings("paginate_number")))->onEachSide(1)
                        ->appends(request()->all());
    }

    public function getContactWithParent($id, $user_id = null) {
        
        return Contact::search(['first_name|last_name', 'first_name', 'last_name', 'whatsapp_contact', 'email_contact', 'sms_contact'])
                        ->where('group_id', $id)
                        ->where("user_id", $user_id)
                        ->filter(['status'])
                        ->latest()
                        ->date()
                        ->paginate(paginateNumber(site_settings("paginate_number")))->onEachSide(1)
                        ->appends(request()->all());
    }

    public function filterMetaData($contact_meta_data, $status) {

        return collect(json_decode($contact_meta_data))->filter(function ($meta_data) use($status) {
            
            return $meta_data->status == $status;
        })->toArray();
    }

    public function statusUpdate($request) {
        
        try {
            $status  = true;
            $reload  = false;
            $message = translate('Contact status updated successfully');
            $contact = Contact::where("id",$request->input('id'))->first();
            $column  = $request->input("column");
            
            if($request->value == StatusEnum::TRUE->status()) {
                
                $contact->status = StatusEnum::FALSE->status();
                $contact->update();
            } else {

                $contact->status = StatusEnum::TRUE->status();
                $contact->update();
            } 

        } catch (\Exception $error) {

            $status  = false;
            $message = $error->getMessage();
        }

        return json_encode([
            'reload'  => $reload,
            'status'  => $status,
            'message' => $message
        ]);
    }

    public function contactMetaData($data) {
         
        if(isset($data["meta_data"])) {

            $refinedAttribute = $data["meta_data"];
                        
            foreach($data["meta_data"] as $key => $value) {

                if($data["meta_data"][$key] != null) {
                
                    $refinedAttribute[explode("::", $key)[0]] = [
                        "value" => $value,
                        "type"  => explode("::", $key)[1]
                    ];
                    unset($refinedAttribute[$key]);
                } else {

                    unset($refinedAttribute[$key]);
                }
            }
            
            return $refinedAttribute;
        }
    }

    public function updateOrInsert($data) {

        Contact::updateOrCreate([
                    
            "uid" => $data["uid"] ?? null

        ], $data);
    }

    public function updateGroupMetaData($data) {

        if(isset($data["meta_data"])) {

            $meta_data = $data["meta_data"];

            foreach($meta_data as &$attribute_values) {

                foreach($attribute_values as $attribute_key => $attribute_value) {

                    if($attribute_key == "value") {

                        $attribute_values["status"] = true;
                    }
                    unset($attribute_values["value"]);
                }
                unset($attribute_values);
            }
        
            $group             = Group::find($data["group_id"]);
            $currentAttributes = json_decode($group->meta_data, true);
            $mergedAttributes  = $currentAttributes ? array_merge($currentAttributes, $meta_data) : $meta_data;
            $newAttributes     = json_encode($mergedAttributes);
            $group->meta_data  = $newAttributes;
            $group->save();
        }
    }

    public function saveSingleContact($user_id, $data) {

        $status = 'success';
        $message = translate('Single contact saved successfully');
     
        try {

            $data["user_id"]   = $user_id;
            $data["meta_data"] = $this->contactMetaData($data);
            unset($data["single_contact"]);
            $this->updateOrInsert($data);
            $this->updateGroupMetaData($data);

        } catch(\Exception $e) {

            $status = 'error';
            $message = translate('System Error: ').$e->getMessage();
        }
        return [
            $status,
            $message
        ];
    }

    public function saveBulkContact($user_id, $data) {
        
        $status  = 'success';
        $message = translate("Your contact upload request is being processed");

        try {

            $locationKeys    = explode(",", $data["location"][0]);
            $values          = explode(",", $data["value"][0]);
            $mappedDataInput = [];
            $i               = 0;
            foreach ($locationKeys as $index => $key) {

                $mappedDataInput[$key] = $values[$i];
                $i++;
            }
            $data["mappedDataInput"] = $mappedDataInput;
            unset($data["single_contact"], $data["file"], $data["import_contact"], $data["location"], $data["value"]);
            $filePath = filePath()["contact"]["path"];
            $mime = explode(".", $data["file__name"])[1];
            $imported = $this->save($this->prepParams($filePath, $mime, $user_id, null, $data));
            ImportJob::dispatch($imported->id);

        } catch (\Exception $e) {

            $status  = 'error';
            $message = translate('Server error: ').$e->getMessage();
        }
        return [
            $status,
            $message
        ];
    }

    public function contactSave($data, $user_id = null) {
        
        if($data["single_contact"] == "true") {
                
            list($status, $message) = $this->saveSingleContact($user_id, $data);
            $notify[] = [$status, $message];

        } else {
            
            list($status, $message) = $this->saveBulkContact($user_id, $data);
            $notify[] = [$status, $message];
        }
        return $notify;
    }

    public function save(array $row): Import {
        
        return Import::create($row);
    }

    public function prepParams(string $path, string $mime, ?int $userId, ?string $type, array $contact_structure): array{

        $data = $contact_structure;
        unset($contact_structure["file__name"], $contact_structure["group_id"]);
        return [
            'user_id'           => $userId,
            'name'              => $data["file__name"],
            'path'              => $path,
            'mime'              => $mime,
            'group_id'          => $data["group_id"],
            'type'              => $type,
            'contact_structure' => $contact_structure,
        ];
    }
    
    public function getCsvRows($file) {

        return Excel::toArray(new ContactImport, $file);
    }

    public function getNewRowData($row_data) {

        $new_row_data = [];
        $keys = array_map(function ($key) {
            return strtolower(str_replace(' ', '_', $key)); 
        }, $row_data[0][0]);
        
        foreach ($row_data[0] as $index => $v) {
            
            $new_row_data[$index] = array_combine($keys, $v);
        }
        return $new_row_data;
    }

    public function saveEachContact($new_row_data, $updated_column, $group_id, $data) {

        $data["meta_data"] = [];
        $meta_data = [];
        
        foreach(array_chunk($new_row_data, 200) as $chunks) {

            foreach($chunks as $values) {
            
                $i = 0;
                $meta_data = []; 
                foreach($values as $column_key => $column_value) {
                    
                    if(array_key_exists($column_key, $data)) {
                        
                        $data[$column_key] = strtolower($column_value);
                    } else {
                        
                        if(isset(array_values($updated_column)[$i][$column_key])) {
                            
                            $meta_data += [
                                $column_key => [
                                    "value" => strtolower($column_value),
                                    "type"  => array_values($updated_column)[$i][$column_key]["type"]
                                ],
                            ];
                        }
                    }
                    $i++;
                } 
                $data["meta_data"] = $meta_data;  
                $data["group_id"] = $group_id;  
                
                Contact::create($data);
            }
        }
    }

    public function importWithNewRow($data, $row_data, $group_id) {

        unset($data["new_row"], $data["file__name"]);
        $updated_column = $this->transformColumns($data["mappedDataInput"]);
        $row_data       = $this->transformRowData($row_data, $updated_column);
        unset($data["mappedDataInput"]);
        $this->saveEachContact($this->getNewRowData($row_data), $updated_column, $group_id, $data);
    }

    public function importWithoutHeader($data, $row_data, $group_id) {

        unset($data["new_row"], $data["file__name"]);
        $updated_column = $this->transformColumns($data["mappedDataInput"]);
        $row_data = $this->transformRowData($row_data, $updated_column);
        unset($data["mappedDataInput"]);
        $new_row_data = $this->getNewRowData($row_data);
        array_splice($new_row_data, 0, 1);
        $this->saveEachContact($new_row_data, $updated_column, $group_id, $data);
    }

    public function importContactFormFile($name, $filePath, $data, $group_id, $user_id = null) {

        $row_data = $this->getCsvRows(storage_path("../../$filePath/$name")); 
        $data["user_id"] = $user_id;

        if($data["new_row"] == "true") {
            
            $this->importWithNewRow($data, $row_data, $group_id);

        } else {
            
            $this->importWithoutHeader($data, $row_data, $group_id);
        }
        unlink(storage_path("../../$filePath/$name"));
    }

    public function transformRowData($row_data, $updated_column) {
        
        $headers = $row_data[0][0];
        $column_mapping = [];
        foreach ($updated_column as $original_column => $updated_column_data) {

            foreach ($updated_column_data as $updated_name => $config) {
                
                $column_mapping[$original_column] = $updated_name;
            }
        }
        $transformed_headers = array_map(function($header) use ($column_mapping) {
            
            return $column_mapping[strtolower(str_replace(' ', '_', str_replace(['(', ')', '?','/'], '', rtrim($header))))] ?? strtolower(str_replace(' ', '_', str_replace(['(', ')', '?','/'], '', rtrim($header))));
        }, $headers);
        $row_data[0][0] = $transformed_headers;
        foreach ($row_data[0] as $index => $data_row) {
            
            $transformed_data = [];
            foreach ($data_row as $key => $value) {
                
                $original_column = $headers[$key];
                $updated_column_name = $column_mapping[$original_column] ?? $original_column;
                if (isset($updated_column[strtolower(str_replace(' ', '_', str_replace(['(', ')', '?','/'], '', rtrim($original_column))))])) {
                    
                    $transformed_data[] = $value;
                }
            }
            $row_data[0][$index] = $transformed_data;
        }
        return $row_data;
    }
        
    public function transformColumns($columns) {
        
        $transformedColumns = [];
        
        foreach ($columns as $key => $value) {

            $parts = explode("::", $value);
            $field = $parts[0];
            $type = isset($parts[1]) ? intval($parts[1]) : null;
            $transformedColumns[$key] = [
                $field => [
                    "status" => true,
                    "type" => $type,
                ]
            ];
        }

        return $transformedColumns;
    }

   
    
    public function getFilePath($folder = "default") {
        $directory = public_path("../../assets/file/contact/$folder");

        if (!File::isDirectory($directory)) {
            File::makeDirectory($directory, 0755, true, true);
        }

        return $directory;
    }

    /**
     * 
     * @param string $uid
     *
     * @return Contact $contact
     */
    public function fetchWithUid(string $uid) {

       return Contact::where("uid", $uid)->first();
    }

    /**
     * 
     * @param string $id
     *
     * @return Contact $contact
     */
    public function fetchWithId(string $uid) {

       return Contact::where("uid", $uid)->first();
    }

    /**
     * 
     * @param string $uid
     *
     * @return array
     */
    public function deleteContact(string $uid): array {

        $contact = $this->fetchWithUid($uid);
        if($contact) {

            $contact->delete();
            $status   = 'success';
            $message = translate("Contact ").$contact->first_name.translate(' has been deleted successfully from admin panel');
        } else {

            $status  = 'error';
            $message = translate("Contact couldn't be found"); 
        }
        return [
            $status, 
            $message
        ];
    }
}