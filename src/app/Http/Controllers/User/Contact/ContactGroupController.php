<?php

namespace App\Http\Controllers\User\Contact;

use App\Models\Group;
use App\Models\Contact;
use Illuminate\View\View;
use App\Traits\ModelAction;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Session;
use App\Http\Requests\ContactGroupRequest;
use App\Service\Admin\Contact\ContactGroupService;
use Illuminate\Validation\ValidationException;

class ContactGroupController extends Controller
{
    use ModelAction;

    public ContactGroupService $contactGroupService;
    public function __construct(ContactGroupService $contactGroupService) { 

        $this->contactGroupService = $contactGroupService;
    }

    /**
     * @return \Illuminate\View\View
     * 
     */
    public function index($id = null):View {
        
        Session::put("menu_active", true);
        $title = translate("Manage Contact Groups");
        $contact_groups = $id ? $this->contactGroupService->getGroupWithChild($id, auth()->user()->id) : $this->contactGroupService->getAllGroups(auth()->user()->id); 
        return view('user.contact.groups.index', compact('title', 'contact_groups'));
    }

    /**
     *
     * @param ContactGroupRequest $request
     * 
     * @return \Illuminate\Http\RedirectResponse
     * 
     */
    public function save(ContactGroupRequest $request) {
        
        $data = $request->all();
        unset($data["_token"]);
        $data = $this->contactGroupService->updateOrCreate($data, auth()->user()->id);
        return back()->withNotify($data);
    }

    /**
     * 
     * @param  \Illuminate\Http\Request $request
     * 
     * @return \Illuminate\Http\JsonResponse
     * 
     * @throws ValidationException If the validation fails.
     * 
     */
    public function statusUpdate(Request $request) {
        
        try {

            $this->validate($request,[

                'id'     => 'required',
                'value'  => 'required',
                'column' => 'required',
            ]); 

            $notify = $this->contactGroupService->statusUpdate($request);
            return $notify;

        } catch (ValidationException $validation) {

            return json_encode([
                'status'  => false,
                'message' => $$validation->errors()
            ]);
        } 
    }

    /**
     * 
     * @param Request $request
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function delete(Request $request) {
  
        $status  = 'error';
        $message = 'Something went wrong';

        try {

            list($status, $message) = $this->contactGroupService->deleteGroup($request->input('uid'));

        } catch (\Exception $e) {

            $status  = 'error';
            $message = translate("Server Error");
        }
        $notify[] = [$status, $message];
        return back()->withNotify($notify);
    }

    /**
     *
     * @param Request $request
     * 
     * @return \Illuminate\Http\RedirectResponse
     * 
     */
    public function bulk(Request $request) :RedirectResponse {

        $status  = 'success';
        $message = translate("Successfully Performed bulk action");
        try {

            list($status, $message) = $this->bulkAction($request, null,[
                "model" => new Group(),
            ]);
    
        } catch (\Exception $exception) {

            $status  = 'error';
            $message = translate("Server Error: ").$exception->getMessage();
        }

        $notify[] = [$status, $message];
        return back()->withNotify($notify);
    }



    public function fetch(Request $request, $type = null) {

        try {
            
            if ($type == "meta_data") {

                $groupIds = $request->input('group_ids');
                $channel = $request->input('channel');
               
                if($groupIds) {

                    $contacts = Contact::where("user_id", auth()->user()->id)
                                            ->whereIn('group_id', $groupIds)
                                            ->where($channel.'_contact', '!=', '')
                                            ->get();

                    if ($contacts->isNotEmpty()) {

                        $groupAttributes = Group::whereIn('id', $groupIds)
                            ->whereNotNull('meta_data')
                            ->pluck('meta_data');
            
                        $mergedAttributes = [];
            
                        foreach ($groupAttributes as $attributes) {
                            $decodedAttributes = json_decode($attributes, true);
            
                            foreach ($decodedAttributes as $key => $attribute) {
    
                                if ($attribute['status'] === true) {
    
                                    if (!isset($mergedAttributes[$key]) || $mergedAttributes[$key] !== $attribute['type']) {
                                        $mergedAttributes[$key] = $attribute['type'];
                                    }
                                }
                            }
                        }
                        return response()->json(['status' => true, 'merged_attributes' => $mergedAttributes]);
                    } else {
    
                        return response()->json(['status' => false, 'message' => "No $channel contacts found for the selected groups"]);
                    }
                }
                else {
                    return response()->json(['status' => false, 'message' => translate("No groups are selected")]);
                }
            }
            
        } catch (\Exception $e) {
            
            $notify[] = ['error', translate('Something Went Wrong')];
            return back()->withNotify($notify);
        }
        
    }
}
