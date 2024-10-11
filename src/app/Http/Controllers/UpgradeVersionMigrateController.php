<?php
namespace App\Http\Controllers;

use App\Enums\StatusEnum;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use App\Models\GeneralSetting;
use App\Models\WhatsappDevice;
use App\Models\WhatsappLog;
use App\Service\Admin\Core\SettingService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\File;

class UpgradeVersionMigrateController extends Controller
{
    public SettingService $settingService;
    public function __construct(SettingService $settingService) { 

        $this->settingService = $settingService;
    }
    public function index() {
        
        $file_path = base_path('update_info.md');
        $file_contents = [];
        $markdownContent = File::get($file_path);
        $sections = explode('## ', $markdownContent);
        array_shift($sections);
        foreach ($sections as $section) {
            
            $section = trim($section);
            list($section_title, $section_content) = explode("\n", $section, 2);
            $file_contents[$section_title] = $section_content;
        }   
        $current_version = site_settings("app_version"); 
        $new_version     = config('requirements.core.appVersion'); 
        $title = "update $new_version";
        return view('update.index', compact(
            'current_version',
            'new_version',
            'title',
            'file_contents'
        ));
    }

    public function update() {
        
        $current_version = site_settings('app_version'); 
        $new_version     = config('requirements.core.appVersion'); 
        $file_path       = base_path('update_info.md');

        if(version_compare($new_version, $current_version, '>')) {
            
            try {
                session(["queue_restart" => true]);

                $migrationFiles = [
                    
                ];
                $dropTableOrColumn = [
                    
                ];
                foreach($migrationFiles as $migrationFile) {

                    Artisan::call('migrate', ['--force' => true, '--path' => $migrationFile ]);
                }   
              
                foreach($dropTableOrColumn as $drop) {

                    Artisan::call('migrate', ['--force' => true, '--path' => $drop ]);
                }
                
                if (File::exists($file_path)) {
                
                    File::delete($file_path);
                }

                Artisan::call('queue:restart');
                Artisan::call('optimize:clear');
                $this->versionUpdate($new_version);
                $notify[] = ['success', 'Succesfully updated database.'];
                return redirect()->route('admin.dashboard')->withNotify($notify);
                
            }catch(\Exception $e) {
                
                $notify[] = ['error', "Internal Error"];
                return back()->withNotify($notify);
            }
        }

        $notify[] = ['error', "No update needed"];
        return back()->withNotify($notify);
    }
    
    public function versionUpdate($new_version) {

        $current_version = [
            
            'app_version' => $new_version
        ];
        $this->settingService->updateSettings($current_version);
        
    }

    public function verify() {
        
        $current_version = site_settings('app_version');
        $new_version     = config('requirements.core.appVersion'); 
        $title           = "update $new_version";
        return view('update.verify', compact(
           
            'current_version',
            'new_version',
            'title'
        ));

    }

    public function store(Request $request) {

        $admin_credentials = $request->validate([
            'username' => ['required'],
            'password' => ['required'],
            
        ]);
        $request->validate([
            'purchased_code' => ['required'],
        ]);
        
        try {
            
            if (Auth::guard('admin')->attempt($admin_credentials)) {

                $buyer_domain   = url()->current();
                $purchased_code = $request->purchased_code;
                $response = Http::withoutVerifying()->get('https://license.igensolutionsltd.com', [
                    'buyer_domain'   => $buyer_domain,
                    'purchased_code' => $purchased_code,
                ]);
               
                if($response->json()['status']) {
                    if(File::exists(base_path('update_info.md'))) {
                        Session::put('is_verified', true);
                        $notify[] = ['success', "Verification Successfull"];
                        return redirect()->route('admin.update.index')->withNotify($notify);
                    } 
                    $notify[] = ['error', "Files are not available"];
                    return back()->withNotify($notify); 
                    
                } else {
                    $notify[] = ['error', "Invalid licence key"];
                    return back()->withNotify($notify);
                }
            }
            return back()->withErrors([
                'email' => 'The provided credentials do not match our records.',
            ]);
        } catch(\Exception $e) {
           
            $notify[] = ['info', "Please Try Again"];
            return back()->withNotify($notify);
        }
    }
}
