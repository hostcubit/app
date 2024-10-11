<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Jobs\ImportJob;
use App\Models\EmailGroup;
use App\Service\ImportContactService;
use Illuminate\Http\Request;
use App\Models\Group;
use App\Models\Contact;
use App\Models\GeneralSetting;
use App\Models\Template;
use Illuminate\Support\Facades\Auth;
use App\Imports\ContactImport;
use App\Exports\ContactExport;
use App\Models\Import;
use App\Rules\ExtensionCheckRule;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;

class PhoneBookController extends Controller
{
    public $importService ;
    public function __construct(ImportContactService $importService)
    {
        $this->importService = $importService;
    }

    /**
     * @return View
     */
    public function groupIndex(Request $request): View
    {
    	$title = "Manage SMS Group";
    	$user = Auth::user();

    	$groups = Group::filter($request)->whereNotNull('user_id')
            ->where('user_id', $user->id)
            ->paginate(paginateNumber(site_settings("paginate_number")));

        $search = $request->search;
        $status = $request->status;

    	return view('user.group.index', compact('title', 'groups', 'status', 'search'));
    }

    public function groupStore(Request $request)
    {
    	$data = $request->validate([
    		'name' => 'required|max:255',
    		'status' => 'required|in:1,2'
    	]);

    	$user = Auth::user();
    	$data['user_id'] = $user->id;
    	Group::create($data);

    	$notify[] = ['success', 'Group has been created'];
    	return back()->withNotify($notify);
    }

    public function groupUpdate(Request $request)
    {
    	$data = $request->validate([
    		'name' => 'required|max:255',
    		'status' => 'required|in:1,2'
    	]);

    	$user = Auth::user();
    	$group = Group::where('user_id', $user->id)
            ->where('id', $request->input('id'))
            ->firstOrFail();

    	$data['user_id'] = $user->id;
    	$group->update($data);

    	$notify[] = ['success', 'Group has been created'];
    	return back()->withNotify($notify);
    }

    public function groupDelete(Request $request)
    {
    	$user = Auth::user();
    	$group = Group::where('user_id', $user->id)->where('id', $request->id)->firstOrFail();
    	Contact::where('user_id', $user->id)->where('group_id', $group->id)->delete();

    	$group->delete();
    	$notify[] = ['success', 'Group has been deleted'];
    	return back()->withNotify($notify);
    }

    /**
     * @param $id
     * @return View
     */
    public function smsContactByGroup($id): View
    {
        $user = Auth::user();
        $group = Group::findOrFail($id);

        $title = "Manage SMS Contact List";
        $contacts = Contact::where('user_id', $user->id)
            ->where('group_id', $id)
            ->with('group')->paginate(paginateNumber(site_settings("paginate_number")));

        return view('user.contact.index', compact('title', 'contacts', 'user', 'group'));
    }

    /**
     * @return View
     */
    public function contactIndex(Request $request): View
    {
        $user = Auth::user();
    	$title = "Manage SMS Contact List";
    	$contacts = $user->contact()->filter($request)->with('group')->latest()->paginate(paginateNumber(site_settings("paginate_number")));
        $search = $request->search;
        $status  = $request->status;
    	return view('user.contact.index', compact('title', 'contacts', 'user', 'status', 'search'));
    }

    public function contactStore(Request $request)
    {
    	$user = Auth::user();
    	$data = $request->validate([
    		'contact_no' => 'required|max:50',
    		'name' => 'required|max:90',
    		'group_id' => 'required|exists:groups,id,user_id,'.$user->id,
    		'status' => 'required|in:1,2'
    	]);

    	$data['user_id'] = $user->id;
    	Contact::create($data);

    	$notify[] = ['success', 'Contact has been created'];
    	return back()->withNotify($notify);
    }

    public function contactUpdate(Request $request)
    {
    	$user = Auth::user();
    	$data = $request->validate([
    		'contact_no' => 'required|max:50',
    		'name' => 'required|max:90',
    		'group_id' => 'required|exists:groups,id,user_id,'.$user->id,
    		'status' => 'required|in:1,2'
    	]);
    	$data['user_id'] = $user->id;
    	$contact = Contact::where('user_id', $user->id)
            ->where('id', $request->input('id'))->firstOrFail();

        $contact->update($data);
    	$notify[] = ['success', 'Contact has been updated'];
    	return back()->withNotify($notify);
    }

    public function contactImport(Request $request)
    {
        $user = Auth::user();
        $request->validate([
            'group_id' => 'required|exists:groups,id,user_id,'.$user->id,
            'file' => ['required', 'file',new ExtensionCheckRule()],
        ]);
        if(Import::where('group_id', $request->input('group_id'))->where('name', request()->file('file')->getClientOriginalName())->where('status', 0)->exists()){
            $notify[] = ['error', 'It seems that you have already uploaded this file! Please wait for a moment while server process your previously uploaded file.'];
            return back()->withNotify($notify);
        }
        $filename = $request->file;

        try {
            $upload = uploadNewFile($filename, filePath()['import']['path']);
            $mime = $filename->getClientMimeType();
            $imported = $this->importService->save($this->importService->prepParams($upload,$mime,$user->id,"sms", $request->input('group_id')));

            ImportJob::dispatch($imported->id);
        } catch (\Exception $exception) {
            $notify[] = ['error', "There's something wrong. Please check your directory permission."];
            return back()->withNotify($notify);
        }

        $notify[] = ['success', 'New contact has been uploaded, it would be sometimes to reload all'];
        return back()->withNotify($notify);
    }

    public function contactExport(Request $request)
    {
        $status = false;
        return Excel::download(new ContactExport($status), 'sms_contact.csv');
    }

    public function contactGroupExport($groupId)
    {
        $status = false;
        $group = Group::where('id', $groupId)->firstOrFail();
        return Excel::download(new ContactExport($status, $groupId), 'sms_contact_'.$group->name.'.csv');
    }

    public function contactDelete(Request $request)
    {
    	$user = Auth::user();
    	$contact = Contact::where('user_id', $user->id)->where('id', $request->id)->firstOrFail();
    	$contact->delete();
    	$notify[] = ['success', 'Contact has been deleted'];
    	return back()->withNotify($notify);
    }

    public function templateIndex()
    {
    	$title = "Manage Template List";
    	$user = Auth::user();
    	$templates = $user->template()->paginate(paginateNumber(site_settings("paginate_number")));
    	return view('user.template.index', compact('title', 'templates'));
    }

    public function templateStore(Request $request)
    {
        $request->validate([
    		'name' => 'required|max:255',
    		'message' => 'required',
    	]);
        $message = '';
    	$user = Auth::user();
    	$data  = Template::create([
			'name' 	  => $request->name,
			'message' => offensiveMsgBlock($request->message),
			'user_id' => $user->id,
			'status'  => 1,
		]);
        if (offensiveMsgBlock($request->message) != $request->message ){
            $message = session()->get('offsensiveNotify') ;
        }
    	$notify[] = ['success', 'Template has been created with '.$message];
    	return back()->withNotify($notify);
    }

    public function templateUpdate(Request $request)
    {
        $request->validate([
    		'name' => 'required|max:255',
    		'message' => 'required',
    	]);
        $message = '';
    	$user = Auth::user();
    	$template = Template::where('user_id', $user->id)->where('id', $request->id)->firstOrFail();
    	$template->update([
			'name' 	  => $request->name,
			'message' => offensiveMsgBlock($request->message),
			'user_id' => $user->id,
			'status'  => 1,
		]);
        if (offensiveMsgBlock($request->message) != $request->message ){
            $message = session()->get('offsensiveNotify') ;
        }
    	$notify[] = ['success', 'Template has been created '.$message];
    	return back()->withNotify($notify);
    }

    public function templateDelete(Request $request)
    {
    	$user = Auth::user();
    	$template = Template::where('user_id', $user->id)->where('id', $request->id)->firstOrFail();
    	$template->delete();
    	$notify[] = ['success', 'Template has been deleted'];
    	return back()->withNotify($notify);
    }

}
