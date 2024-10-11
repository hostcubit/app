<?php

namespace App\Service\Admin\Core;

use Image;
use App\Enums\StatusEnum;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Schema;
use Maatwebsite\Excel\HeadingRowImport;

class FileService
{
   /**
     * @param \Illuminate\Http\UploadedFile $file
     * 
     * @param string $key
     * 
     * @return string|null
     * 
     */
    public function uploadFile(UploadedFile $file, $key = null, $file_path = null, $file_size = null, $delete_file = true) {

        $config      = config('setting.file_path');
        $filePath    = $config[$key]['path'] ?? $file_path;
        $fileSize    = $config[$key]['size'] ?? $file_size;
      
        if ($filePath) {
            
            if (!file_exists($filePath)) {
                mkdir($filePath, 0755, true);
            }

            $file_name = uniqid() . time() . '.' . $file->getClientOriginalExtension();
            $fileDestination = $filePath . '/' . $file_name;

            $oldFile = glob($filePath . '/*');
            if ($oldFile && $delete_file) {
                unlink($oldFile[0]);
            }

            // Check if the file is an SVG
            if ($file->getClientOriginalExtension() === 'svg') {
                // Move the SVG file directly without using Image intervention
                $file->move($filePath, $file_name);
            } else {
                
                $image = Image::make(file_get_contents($file));
                if ($fileSize) {
                    $size = explode('x', strtolower($fileSize));
                   
                    $image->resize($size[0], $size[1], null, function ($constraint) {
                        $constraint->aspectRatio();
                        $constraint->upsize();
                    });
                }
               
                if (site_settings("store_as_webp") == StatusEnum::TRUE->status() && in_array($file->getClientOriginalExtension(), ['jpeg', 'jpg', 'png', 'gif'])) {
                    $image->encode('webp', 90); 
                    $file_name = str_replace('.' . $file->getClientOriginalExtension(), '.webp', $file_name);
                    $fileDestination = $filePath . '/' . $file_name;
                }
                
                $image->save($fileDestination);
            }
            
            return $file_name;
        }

        return null;
    }



    public function generateContactDemo($type, $condition_exlude = [], $allow_attribute = false) {

        $contact_columns = Schema::getColumnListing('contacts');
        $first_name_key = array_search("first_name", $contact_columns);
        if ($first_name_key !== false) {

            unset($contact_columns[$first_name_key]);
            array_unshift($contact_columns, "first_name");
        }

        $columns_to_exclude = [
            'attributes', 
            'created_at', 
            'group_id', 
            'id', 
            'status', 
            'uid', 
            'updated_at', 
            'user_id'
        ];
        $columns_to_exclude = array_merge($columns_to_exclude, $condition_exlude);
        $contact_columns   = array_diff($contact_columns, $columns_to_exclude);
        if($allow_attribute) {

            $attributes         = json_decode(site_settings("contact_meta_data"));   
            $filteredAttributes = collect($attributes)->filter(function ($attribute) {

                return $attribute->status === true;
            })->toArray();
            $columns = array_keys($filteredAttributes);
            $attribute_type = collect($filteredAttributes)->map(function ($attribute) {
                return $attribute->type ?? null;
            })->toArray();

            $contact_columns = array_merge($contact_columns, $columns);
        }
			
        $data = $this->getData($contact_columns, [], $allow_attribute); 
  
        if ($type == 'csv') {
            
            $filePath = $this->generateCsvFile($data);
        } elseif ($type == 'xlsx') {

            $filePath = $this->generateExcelFile($data);
        } else {

            throw new \InvalidArgumentException('Unsupported file type.');
        }
        return $filePath;
    }

    function generateCsvFile($data) {

		$data     = [ $data ];
		$file_name = "demo.csv";
		$file     = Excel::store(new \App\Exports\ContactDemoExport($data), $file_name, "contact_demo", \Maatwebsite\Excel\Excel::CSV);
		$files    = File::files(config("filesystems.disks.contact_demo.root"));

		foreach ($files as $file) {

			if (pathinfo($file, PATHINFO_EXTENSION) == "csv" && basename($file) != $file_name) {

				File::delete($file);
			}
		}
		if($file) {

			return config("filesystems.disks.contact_demo.root")."/".$file_name;
		}
	}

	public function generateExcelFile($data) {

		$data = [ $data ];
		$file_name = "demo.xlsx";
		$file = Excel::store(new \App\Exports\ContactDemoExport($data), $file_name, "contact_demo", \Maatwebsite\Excel\Excel::XLSX);
		$files = File::files(config("filesystems.disks.contact_demo.root"));
		foreach ($files as $file) {
			if (pathinfo($file, PATHINFO_EXTENSION) == "xlsx" && basename($file) != $file_name) {

				File::delete($file);
			}
		}
		if($file) {
			return config("filesystems.disks.contact_demo.root")."/".$file_name;
		}
	}

	public function getData($contact_columns, $attribute_type, $allow_attribute) {
		
		$data= [];
		foreach($contact_columns as $column) {

			if($column == "full_name" ) {

				$data[textFormat(["_"], $column)] = generateText("first_name")." ".generateText("last_name");
			}
			if($column == "first_name" ) {

				$data[textFormat(["_"], $column)] = generateText("first_name");
			}
			if($column == "last_name" ) {

				$data[textFormat(["_"], $column)] = generateText("last_name");
			}
			if($column == "email_contact" ) {

				$data['contact'] = generateText("email");
			}
			if($column == "sms_contact" ) {

				$data['contact'] = (string)rand(10000000, 99999999);
			}
			if($column == "whatsapp_contact" ) {

				$data['contact'] = (string)rand(10000000, 99999999);
			}
		}

		if($allow_attribute) {

			foreach($attribute_type as $attribute_name => $type) {
				
				if ($type == \App\Models\GeneralSetting::DATE) {

					$data[textFormat(["_"], $attribute_name)] = (string)now()->addDays(rand(1, 30))->toDateTimeString();
				}
				
				if ($type == \App\Models\GeneralSetting::BOOLEAN) {

					$data[textFormat(["_"], $attribute_name)] = (rand(0, 1) == 1) ? 'Yes' : 'No';
				}
				
				if ($type == \App\Models\GeneralSetting::NUMBER) {

					$data[textFormat(["_"], $attribute_name)] = (string)rand(100000, 999999);
				}
				
				if ($type == \App\Models\GeneralSetting::TEXT) {

					$data[textFormat(["_"], $attribute_name)] = generateText("object");
				}
			}
		}
		return $data;
	}

    public function UploadContactFile($file) {

        $extension = $file->getClientOriginalExtension();
        $directory = "assets/file/contact/temporary";
        if (!File::isDirectory($directory)) {

            File::makeDirectory($directory, 0755, true, true);
        }
        $file_name = 'temp_' . time() . '.' . $extension;
        $file->move($directory, $file_name);
        $filePath = "assets/file/contact/temporary/{$file_name}";

        return [
            $file_name,
            $filePath
        ];
    }

    public function deleteContactFile($file_name) {
        
        $status   = false;
        $message  = "Something went wrong";  
        $filePath = "assets/file/contact/temporary/{$file_name}";
        
        if(File::exists($filePath)) {

            File::delete($filePath);
            $status  = true;
            $message = null;
        } else {

            $message = translate("File Not Found");
        }
        return [$status, $message];
    }

    public function parseContactFile($filePath) {

        return $this->parseData($filePath);
    }

    private function parseData($filePath): array {

        $data = (new HeadingRowImport)->toArray($filePath);
        $headerRow = $data[0][0];
        $headers = array_combine(array_map([$this, 'getExcelColumnName'], range(1, count($headerRow))), $headerRow);
        
        return $headers;
    }

    private function getExcelColumnName(int $columnNumber): string {

        $dividend = $columnNumber;
        $columnName = '';
       
        while ($dividend > 0) {
           
            $modulo = ($dividend - 1) % 26;
            $columnName = chr(65 + $modulo) . $columnName;
            $dividend = (int)(($dividend - $modulo) / 26);
        }
        return $columnName . '1';
    }

    public function readCsv($contacts) {
        $meta_data = [];
    
        $filePath = $contacts->getPathname();
        $file = fopen($filePath, 'r');
        $headers = fgetcsv($file);
    
        while (($row = fgetcsv($file)) !== false) {
            $data = array_combine($headers, $row);
            $contact = [];
            foreach ($data as $key => $value) {
                $formatted_key = strtolower(textFormat([' '], $key, '_'));
                $contact[$formatted_key] = $value; 
            }
            $meta_data[] = $contact;
        }
        fclose($file);
    
        return $meta_data;
    }
}