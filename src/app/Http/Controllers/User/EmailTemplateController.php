<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\BeeTemplate;
use App\Models\EmailTemplates;
use Illuminate\Http\Request;

class EmailTemplateController extends Controller
{


     /**
     * custom build template method 
     */
    public function templates(Request $request){
        
        $title = "Admin Mail template";

        if($request->ajax()){
    
            return response()->json([
                'view' => view('user.email_template.data',['templates'=> EmailTemplates::where('user_id',auth()->user()->id)->whereNotNull('provider')->where('status',1)->get()]
                )->render()
            ],'200' );
        }
        $emailTemplates = EmailTemplates::where('user_id',auth()->user()->id)->whereNotNull('provider')->latest()->paginate(paginateNumber(site_settings("paginate_number")));
        return view('user.email_template.templates', compact('title', 'emailTemplates'));
     }
    

    
    /**
     * create a template
     */

     public function create(){
        $title = "Mail template Create";
        $beeTemplates = BeeTemplate::all();
        return view('user.email_template.create', compact('title','beeTemplates'));
     }


     /**
      * stroe a mail templates
      */
     public function store(Request $request){

        $request->validate([
            'name' => 'required|unique:email_templates,name',
            'template_html'=>'required_if:provider,1',
            'bee_template_json'=>'required_if:provider,1',
            'body'=>'required_if:provider,2',
            "provider" => "required",
        ]);

        $body = request()->provider ==  2 ? request()->body : request()->template_html;
        $template_json = request()->provider ==  2 ? null : request()->bee_template_json;

        $template =  new EmailTemplates();
        $template->name = $request->name;
        $template->user_id = auth()->user()->id;
        $template->body = $body;
        $template->status = 2;
        $template->provider = request()->provider;
        $template->template_json = $template_json;
        $template->save();
        $notify[] = ['success', translate('Email template has been Created')];
        return back()->withNotify($notify);
     }


     /**
      * edit template
      *
      * @param Request $request
      * @return void
      */

      public function editTemplate($id){
            $title = "Mail template Edit";
            $beeTemplates = BeeTemplate::all();
            $template = EmailTemplates::where('user_id',auth()->user()->id)->where('id',$id)->first();
            return view('user.email_template.edit_template', compact('title','beeTemplates','template'));
      }

     /**
      * update  a mail templates
      *
      * @param Request $request
      * @return void
      */
     public function updateTemplates(Request $request){

        $request->validate([
            'name' => 'required|unique:email_templates,name,'.request()->id,
            'template_html'=>'required_if:provider,1',
            'bee_template_json'=>'required_if:provider,1',
            'body'=>'required_if:provider,2',
            "provider" => "required",
        ]);
        $body = $request->body;
        $bee_template_json = null;
        if($request->provider == 1){
           $body = $request->template_html;
           $bee_template_json = $request->bee_template_json;
        }
        $template = EmailTemplates::where('user_id',auth()->user()->id)->where('id',$request->id)->first();
        $template->body = $body;
        $template->template_json = $bee_template_json;
        $template->save();
        $notify[] = ['success', translate('Email template has been Updated')];
        return back()->withNotify($notify);
     }


    /**
     * get pre-designed template by id
     *
     */
    public function templateJson($id){
     
        return json_decode(BeeTemplate::find($id))->template;
    }

    /**
     * get template json  by id
     *
     */
    public function templateJsonEdit($id){

 
        return json_decode( EmailTemplates::where('id',$id)->first()->template_json);
    }

    public function delete(Request $request){
        EmailTemplates::where('user_id',auth()->user()->id)->where('id',$request->id)->delete();
        $notify[] = ['success', translate('Email template has been Updated')];
        return back()->withNotify($notify);
    }
}
