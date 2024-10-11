<?php

namespace App\Http\Middleware;

use Closure;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AccessMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next)
    {
        
        $i              = 0;
        $routes         = [];
        $messages       = [];
        $pass           = false;
      	$notify	    	= [];
        $route_types    = config("planaccess.types");
        $current_route  = request()->route()->getName();
		
        foreach(config("planaccess.routes") as $key => $value) {
            $routes[]   = $key;
            $messages[] = $value;
        }
        
        try {
            
            if(!auth()->user()->runningSubscription()) {
                
                $notify[] = ['error', 'Purchase A Subscription Plan'];
                return back()->withNotify($notify);
            } 
            
            if(planAccess(auth()->user())) {

                $allowed_access = planAccess(auth()->user());
                
                foreach(array_slice($allowed_access, 1) as $k => $v) {
                   
                    if($allowed_access == "0" && ($k == "sms" || $k ="email") && !array_key_exists("allowed_gateways", $allowed_access[$k])){

                        $notify[] = ["info", "Current Plan Does Not give you permission to create gateways."];
                        return redirect()->route('user.dashboard')->withNotify($notify);   
                    }
                }
                
            } else {
                $notify[] = ["info", "Request Admin to update Subscription Plan"];
                return redirect()->route('user.dashboard')->withNotify($notify);    
            }
        
            foreach($routes as $route) {
               	
              	$pass = false;
                $route = explode('_', $route);
                $associativeRoutes[$route[0]] = implode('_', array_slice($route, 1));
                
                foreach($associativeRoutes as $route_key => $route_name) {
					
                    if($allowed_access && stripos($current_route, $route_name) !== false) {
                      	
                        foreach($route_types as $route_type) {
                           	
                            switch ($route_type) {
                                case ($route_key != "settings" && stripos($route_name, $route_type) !== false && $allowed_access[$route_type == "mail" ? "email" : $route_type]["is_allowed"]) :
                                    $pass = true;
                                    break;
                                case ($route_key == "settings" && (Auth::user()->runningSubscription()->currentPlan()->sms->android->is_allowed == true || Auth::user()->sms_gateway  == 2)) : 
                                    $pass = true;
                                    break;
                                case (Auth::user()->runningSubscription()->currentPlan()->sms->android->is_allowed == true && Auth::user()->sms_gateway  == 2) :
                                    $pass = true;
                                    break;
                            }
                        } 
                        $notify[] = ['error', translate($messages[$i])];
                      	if($pass == false) {
                          	
                        	break 2;
                        }
                    } else {

                      	$pass = true;
                      	
                    }
                }
                $i++;
            }
          	return $pass == true ? $next($request) : redirect()->route('user.dashboard')->withNotify($notify);   
        }
        catch(\Exception $e) {
            
            redirect()->route('user.dashboard')->withNotify("error", "Internal Error");
        }
    }
}
