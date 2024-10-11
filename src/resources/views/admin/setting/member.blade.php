@push("style-include")
  <link rel="stylesheet" href="{{ asset('assets/theme/global/css/select2.min.css')}}">
@endpush 
@extends('admin.layouts.app')
@section("panel")
  <main class="main-body">
    <div class="container-fluid px-0 main-content">
      <div class="page-header">
        <div class="page-header-left">
          <h2>{{ $title }}</h2>
          <div class="breadcrumb-wrapper">
            <nav aria-label="breadcrumb">
              <ol class="breadcrumb">
                <li class="breadcrumb-item">
                  <a href="{{ route("admin.dashboard") }}">{{ translate("Dashboard") }}</a>
                </li>
                <li class="breadcrumb-item active" aria-current="page"> {{ $title }} </li>
              </ol>
            </nav>
          </div>
        </div>
      </div>
      <div class="pill-tab mb-4">
        <ul class="nav" role="tablist">
         
          <li class="nav-item" role="presentation">
            <a class="nav-link active" data-bs-toggle="tab" href="#authentication" role="tab" aria-selected="true">
              <i class="ri-notification-2-line"></i> 
              {{ translate("Authentication Settings") }} 
            </a>
          </li>

          <li class="nav-item" role="presentation">
            <a class="nav-link" data-bs-toggle="tab" href="#onboarding" role="tab" aria-selected="false" tabindex="-1">
              <i class="ri-android-line"></i> 
              {{ translate("Onboarding Settings") }} 
            </a>
          </li>
        </ul>
      </div>
      <div class="tab-content">
        <div class="tab-pane active fade show" id="authentication" role="tabpanel">
            <div class="card">
                <div class="form-header">
                <h4 class="card-title">{{ translate("Authentication Settings") }}</h4>
                </div>
                <div class="card-body pt-0">
                    <form action="{{ route("admin.system.setting.store") }}" method="POST" enctype="multipart/form-data" class="settingsForm">
                        @csrf
                        <div class="form-element">
                            <div class="row gy-4">
                                <div class="col-xxl-2 col-xl-3">
                                <h5 class="form-element-title">{{ translate("Authentication") }}</h5>
                                </div>
                                <div class="col-xxl-8 col-xl-9">
                                    <div class="row gy-4">
                                        @foreach(json_decode(site_settings("member_authentication"), true) as $auth_key => $auth_param)

                                            <div class="col-md-6">
                                                @if($auth_key == "login_with")
                                                    <div class="form-inner">
                                                        <label for="login_with" class="form-label">{{ translate("Login With") }}</label>
                                                        <select data-placeholder="{{ translate("Choose member login parameters") }}" class="form-select select2-search" name="site_settings[member_authentication][{{ $auth_key }}][]" data-show="5" id="login_with" multiple="multiple">
                                                            <option value=""></option>
                                                            @foreach(config('setting.login_attribute')  as $auth )
                                                                <option @if(in_array($auth , json_decode(site_settings("member_authentication"), true)['login_with'] ?? [] )) selected @endif   value="{{$auth}}">{{$auth}}</option>
                                                            @endforeach
                                                        </select>
                                                    </div>
                                                @else
                                                    <div class="form-inner">
                                                        <label class="form-label"> {{ translate("Member ".textFormat(['_'], $auth_key, ' ')) }} </label>
                                                        <div class="form-inner-switch">
                                                            <label class="pointer" for="member_authentication_{{ $auth_key }}">{{ translate("Turn on/off Member ".textFormat(['_'], $auth_key, ' ')) }}</label>
                                                            <div class="switch-wrapper mb-1 checkbox-data">
                                                                <input {{ $auth_param == \App\Enums\StatusEnum::TRUE->status() ? 'checked' : '' }} value="{{ \App\Enums\StatusEnum::TRUE->status() }}" type="checkbox" class="switch-input" id="member_authentication_{{ $auth_key }}" name="site_settings[member_authentication][{{ $auth_key }}]"/>
                                                                <label for="member_authentication_{{ $auth_key }}" class="toggle">
                                                                <span></span>
                                                                </label>
                                                            </div>
                                                        </div>
                                                    </div>
                                                @endif
                                            </div>
                                            
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="form-element">
                            <div class="row gy-4">
                                <div class="col-xxl-2 col-xl-3">
                                <h5 class="form-element-title">{{ translate("Verification Code") }}</h5>
                                </div>
                                <div class="col-xxl-8 col-xl-9">
                                <div class="row gy-4">
                                    <div class="col-md-12 parent">
                                        <div class="form-inner">
                                            <label class="form-label"> {{ translate("OTP Verification") }} </label>
                                            <div class="form-inner-switch">
                                            <label class="pointer" for="registration_otp_verification">{{ translate("Turn on/off otp verification") }}</label>
                                            <div class="switch-wrapper mb-1 checkbox-data">
                                                <input {{ site_settings("registration_otp_verification") == \App\Enums\StatusEnum::TRUE->status() ? 'checked' : '' }} type="checkbox" class="switch-input" id="registration_otp_verification" name="site_settings[registration_otp_verification]"/>
                                                <label for="registration_otp_verification" class="toggle">
                                                <span></span>
                                                </label>
                                            </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-12 child">
                                        <div class="form-inner">
                                            <label class="form-label"> {{ translate("Email OTP Verification") }} </label>
                                            <div class="form-inner-switch">
                                            <label class="pointer" for="email_otp_verification">{{ translate("Turn on/off Email otp verification") }}</label>
                                            <div class="switch-wrapper mb-1 checkbox-data">
                                                <input {{ site_settings("email_otp_verification") == \App\Enums\StatusEnum::TRUE->status() ? 'checked' : '' }} type="checkbox" class="switch-input" id="email_otp_verification" name="site_settings[email_otp_verification]"/>
                                                <label for="email_otp_verification" class="toggle">
                                                <span></span>
                                                </label>
                                            </div>
                                            </div>
                                            <p class="form-element-note text-danger">{{ translate("Requires a Default Email Gateway.")}} <a href="{{ route('admin.gateway.email.index') }}">{{ translate("Set up gateway") }}</a> </p>
                                        </div>
                                    </div>
                                </div>
                                </div>
                            </div>
                        </div>
                    
                        <div class="row">
                            <div class="col-xxl-10">
                                <div class="form-action justify-content-end">
                                    <button type="reset" class="i-btn btn--danger outline btn--md"> {{ translate("Reset") }} </button>
                                    <button type="submit" class="i-btn btn--primary btn--md"> {{ translate("Save") }} </button>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <div class="tab-pane fade" id="onboarding" role="tabpanel">
            <div class="card">
                <div class="form-header">
                <h4 class="card-title">{{ translate("Onboarding Settings") }}</h4>
                </div>
                <div class="card-body pt-0">
                    <form action="{{ route("admin.system.setting.store") }}" method="POST" enctype="multipart/form-data" class="settingsForm">
                        @csrf
                        <div class="form-element">
                            <div class="row gy-4">
                                <div class="col-xxl-2 col-xl-3">
                                    <h5 class="form-element-title">{{ translate("Rewards") }}</h5>
                                    </div>
                                    <div class="col-xxl-8 col-xl-9">
                                    <div class="row gy-4">
                                        <div class="col-md-12">
                                            <div class="form-inner">
                                                <label class="form-label"> {{ translate("Onboarding Bonus") }} </label>
                                                <div class="form-inner-switch parent">
                                                <label class="pointer" for="onboarding_bonus">{{ translate("Turn on/off onboarding registration") }}</label>
                                                <div class="switch-wrapper mb-1">
                                                    <input {{ site_settings("onboarding_bonus") == \App\Enums\StatusEnum::TRUE->status() ? 'checked' : '' }} type="checkbox" class="switch-input" id="onboarding_bonus" name="site_settings[onboarding_bonus]"/>
                                                    <label for="onboarding_bonus" class="toggle">
                                                    <span></span>
                                                    </label>
                                                </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-12">
                                            <div class="form-inner child">
                                                <label for="onboarding_bonus_plan" class="form-label">{{ translate("Onboarding Reward Plan") }}</label>
                                                <select data-placeholder="{{translate('Select a plan')}}" class="form-select select2-search" name="site_settings[onboarding_bonus_plan]" data-show="5" id="onboarding_bonus_plan">
                                                    <option value=""></option>
                                                    @foreach($plans as $plan )
                                                        <option {{ site_settings("onboarding_bonus_plan") == $plan->id ? 'selected' : '' }} value="{{$plan->id}}">{{$plan->name}}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-xxl-10">
                                <div class="form-action justify-content-end">
                                <button type="reset" class="i-btn btn--danger outline btn--md"> {{ translate("Reset") }} </button>
                                <button type="submit" class="i-btn btn--primary btn--md"> {{ translate("Save") }} </button>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
      </div>
    </div>
  </main>
@endsection

@push("script-include")
  <script src="{{asset('assets/theme/global/js/select2.min.js')}}"></script>  
@endpush
@push("script-push")

  <script>
    "use strict";
    $(document).ready(function() {
       
        select2_search($("#onboarding_bonus_plan").attr("data-placeholder"));
        setInitialVisibility();
        updateBackgroundClass();
        $('.parent input[type="checkbox"]').change(function() {

            toggleChildren();
        });

        $('.switch-input').on('change', function() {

            updateBackgroundClass();
        });
        $('form').on('submit', function(e) {
            
            $('.checkbox-data').each(function() {
                var $checkbox = $(this).find('.switch-input');
                var $hiddenInput = $(this).find('input[type="hidden"]');

                if ($checkbox.is(':checked')) {
                    if ($hiddenInput.length === 0) {
                        $(this).append('<input type="hidden" name="' + $checkbox.attr('name') + '" value="{{ \App\Enums\StatusEnum::TRUE->status() }}">');
                    } else {
                        $hiddenInput.val('{{ \App\Enums\StatusEnum::TRUE->status() }}');
                    }
                } else {
                    if ($hiddenInput.length === 0) {
                        $(this).append('<input type="hidden" name="' + $checkbox.attr('name') + '" value="{{ \App\Enums\StatusEnum::FALSE->status() }}">');
                    } else {
                        $hiddenInput.val('{{ \App\Enums\StatusEnum::FALSE->status() }}');
                    }
                }
            });
      });
    });
    
  </script>
@endpush
