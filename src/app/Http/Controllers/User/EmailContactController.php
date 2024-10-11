<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Jobs\ImportJob;
use App\Service\ImportContactService;
use Illuminate\Http\Request;
use App\Models\EmailGroup;
use App\Models\EmailContact;
use Illuminate\Support\Facades\Auth;
use App\Exports\EmailContactExport;
use App\Models\Import;
use App\Rules\ExtensionCheckRule;
use Maatwebsite\Excel\Facades\Excel;

class EmailContactController extends Controller
{
    public ImportContactService $importService;
    public function __construct(ImportContactService $importService)
    {
        $this->importService = $importService;
    }

    public function emailGroupIndex(Request $request)
    {
    	$title = "Manage Email Group";
    	$user = Auth::user();
    	$groups = $user->emailGroup()->filter($request)->paginate(paginateNumber(site_settings("paginate_number")));
    	return view('user.email_group.index', compact('title', 'groups'));
    }

    public function emailGroupStore(Request $request)
    {
    	$data = $request->validate([
    		'name' => 'required|max:255',
    		'status' => 'required|in:1,2'
    	]);

    	$user = Auth::user();
    	$data['user_id'] = $user->id;
    	EmailGroup::create($data);
    	$notify[] = ['success', 'Email Group has been created'];
    	return back()->withNotify($notify);
    }

    public function emailGroupUpdate(Request $request)
    {
    	$data = $request->validate([
    		'name' => 'required|max:255',
    		'status' => 'required|in:1,2'
    	]);

    	$user = Auth::user();
    	$group = EmailGroup::where('user_id', $user->id)->where('id', $request->id)->firstOrFail();
    	$data['user_id'] = $user->id;
    	$group->update($data);
    	$notify[] = ['success', 'Email Group has been created'];
    	return back()->withNotify($notify);
    }

    public function emailGroupdelete(Request $request)
    {
    	$user = Auth::user();
    	$group = EmailGroup::where('user_id', $user->id)->where('id', $request->id)->firstOrFail();
    	$contact = EmailContact::where('user_id', $user->id)->where('email_group_id', $group->id)->delete();
    	$group->delete();
    	$notify[] = ['success', 'Email Group has been deleted'];
    	return back()->withNotify($notify);
    }

    public function emailContactByGroup(Request $request, $id)
    {
        $group = EmailGroup::where('id', $id)->firstOrFail();
        $title = "Manage Email Contact List";
        $user = Auth::user();
        $contacts = EmailContact::filter($request)->where('user_id', $user->id)->where('email_group_id', $id)->with('emailGroup')->paginate(paginateNumber(site_settings("paginate_number")));
        $search = $request->search;
        $status = $request->status;
        
        return view('user.email_contact.index', compact('title', 'contacts', 'user', 'group', 'search', 'status'));
    }


    public function emailContactIndex(Request $request)
    {
        $title = "Manage Email Contact List";
        $user = Auth::user();
        $contacts = $user->emailContact()->filter($request)->latest()->with('emailGroup')->paginate(paginateNumber(site_settings("paginate_number")));
        return view('user.email_contact.index', compact('title', 'contacts', 'user'));
    }

    public function emailContactStore(Request $request)
    {
        $user = Auth::user();
        $data = $request->validate([
            'email' => 'required|email|max:120',
            'name' => 'required|max:90',
            'email_group_id' => 'required|exists:groups,id,user_id,'.$user->id,
            'status' => 'required|in:1,2'
        ]);
        $data['user_id'] = $user->id;
        EmailContact::create($data);
        $notify[] = ['success', 'Email Contact has been created'];
        return back()->withNotify($notify);
    }

    public function emailContactUpdate(Request $request)
    {
        $user = Auth::user();
        $data = $request->validate([
            'email' => 'required|email|max:120',
            'name' => 'required|max:90',
            'email_group_id' => 'required|exists:groups,id,user_id,'.$user->id,
            'status' => 'required|in:1,2'
        ]);
        $data['user_id'] = $user->id;
        $contact = EmailContact::where('user_id', $user->id)->where('id', $request->id)->firstOrFail();
        $contact->update($data);
        $notify[] = ['success', 'Email Contact has been updated'];
        return back()->withNotify($notify);
    }

    public function emailContactImport(Request $request)
    {
        $user = Auth::user();
        $request->validate([
            'email_group_id' => 'required|exists:groups,id,user_id,'.$user->id,
            'file' => ['required', 'file',new ExtensionCheckRule()],
        ]);

        if(Import::where('group_id', $request->input('email_group_id'))->where('name', request()->file('file')->getClientOriginalName())->where('status', 0)->exists()){
            $notify[] = ['error', 'You Already Uploaded This File!! Please Wait, Your Previous Uploaded File is under Processing'];
            return back()->withNotify($notify);
        }
        $filename = $request->file;

        try {
            $upload = uploadNewFile($filename, filePath()['import']['path']);
            $mime = $filename->getClientMimeType();
            $imported = $this->importService->save($this->importService->prepParams($upload,$mime,$user->id,"email", $request->input('email_group_id')));
            ImportJob::dispatch($imported->id);
        } catch (\Exception $exception) {
            $notify[] = ['error', "There's something wrong."];
            return back()->withNotify($notify);
        }

        $notify[] = ['success', 'Email Contact data has been imported'];
        return back()->withNotify($notify);
    }

    public function emailContactExport()
    {
        $status = false;
        return Excel::download(new EmailContactExport($status), 'email_contact.csv');
    }

    public function emailContactGroupExport($groupId)
    {
        $status = false;
        $group = EmailGroup::where('id', $groupId)->firstOrFail();
        return Excel::download(new EmailContactExport($status, $groupId), 'email_group_'.$group->name.'.csv');
    }

    public function emailContactDelete(Request $request)
    {
        $user = Auth::user();
        $contact = EmailContact::where('user_id', $user->id)->where('id', $request->id)->firstOrFail();
        $contact->delete();
        $notify[] = ['success', 'Email Contact has been deleted'];
        return back()->withNotify($notify);
    }
}
