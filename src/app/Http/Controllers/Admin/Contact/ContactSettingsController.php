<?php

namespace App\Http\Controllers\Admin\Contact;

use App\Enums\StatusEnum;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Session;
use App\Service\Admin\Core\SettingService;
use App\Http\Requests\ContactSettingsRequest;
use App\Service\Admin\Core\CollectionService;
use App\Service\Admin\Contact\ContactSettingsService;

class ContactSettingsController extends Controller
{
    public SettingService $settingService;
    public ContactSettingsService $contactSettingService;

    public function __construct(SettingService $settingService, ContactSettingsService $contactSettingService) { 

        $this->contactSettingService = $contactSettingService;
        $this->settingService        = $settingService;
    }

    /**
     * @return \Illuminate\View\View
     * 
     */
    public function index() {

        Session::put("menu_active", true);
        $title     = translate("Manage Contact Attributes");
        $meta_data = new CollectionService(collect(json_decode(site_settings('contact_meta_data'), true)));
        $meta_data = $meta_data->collectionSearch([])
                                ->keyFilter(last(explode('.', request()->route()->getName())))
                                ->paginate(paginateNumber(site_settings("paginate_number")));
                                
        return view('admin.contact.settings.index', compact('title', 'meta_data'));
    }

    /** 
     * Contact Settings->Attributes Store
     * @param ContactSettingsRequest $request
     */ 
    public function metaSave(ContactSettingsRequest $request) {

        $status  = 'success';
        $message = $request->has('old_attribute_name') ? translate("Contact attribute updated successfully") : translate("New contact attribute added");
        try {

            $data = $request->all();
            unset($data['_token']);
            $final_data = $this->contactSettingService->save($data);
            $this->settingService->updateSettings($final_data);

        } catch(\Exception $e) {

            $message = translate("Server Error");
        }
        $notify[] = [$status, $message];
        return back()->withNotify($notify);
    }

    /** 
     * Contact Settings->Attributes delete
     * @param Request $request
    */ 
    public function metaDelete(Request $request) {
        
        $status  = 'success';
        $message = translate("Contact Attribute deleted");
        try {

            $data = $request->all();
            unset($data['_token']);
            $final_data = $this->contactSettingService->delete($data);
            $this->settingService->updateSettings($final_data);

        } catch(\Exception $e) {

            $message = translate("Server Error");
        }
        $notify[] = [$status, $message];
        return back()->withNotify($notify);
    }

   /**
     * @param  \Illuminate\Http\Request $request
     * 
     * @return \Illuminate\Http\JsonResponse
     * 
     */
    public function metaStatusUpdate(Request $request) {
        
        
        try {
            $this->validate($request,[
                'name' => 'required',
            ]);

            $status   = true;
            $reload   = false;
            $message  = translate('Contact Attribute status updated successfully');

            $data['contact_meta_data'] = json_decode(site_settings('contact_meta_data'), true);
            
            if ($data['contact_meta_data'][$request->input('name')]['status'] == StatusEnum::TRUE->status()) {

                $data['contact_meta_data'][$request->input('name')]['status'] = StatusEnum::FALSE->status();
            } else {

               $data['contact_meta_data'][$request->input('name')]['status'] = StatusEnum::TRUE->status();
            } 
            $this->settingService->updateSettings($data);
            return json_encode([
                'reload'  => $reload,
                'status'  => $status,
                'message' => $message
            ]);
            
        } catch (\Illuminate\Validation\ValidationException $validation) {

                $status  = false;
                $message = $validation->errors();

        } catch (\Exception $error) {

            $status = false;
            $message = $error->getMessage();
        }
        return json_encode([
            'status'  => $status,
            'message' => $message
        ]);
    }
}
