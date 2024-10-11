@push("style-include")
  <link rel="stylesheet" href="{{ asset('assets/theme/global/css/select2.min.css')}}">
@endpush
@extends('admin.layouts.app')
@section('panel')
<main class="main-body">
    <div class="container-fluid px-0 main-content">
      <div class="page-header">
        <div class="page-header-left">
          <h2>{{ translate('User information')}}</h2>
          <div class="breadcrumb-wrapper">
            <nav aria-label="breadcrumb">
              <ol class="breadcrumb">
                <li class="breadcrumb-item">
                  <a href="{{ route('admin.dashboard') }}">{{ translate("Dashboard") }}</a>
                </li>
                <li class="breadcrumb-item active" aria-current="page">
                  {{ translate("User Profile") }}
                </li>
              </ol>
            </nav>
          </div>
        </div>
      </div>
      
      <div class="row g-4">
        <div class="col-12">
          <div class="row g-4">
            <div class="col-xxl-3 col-lg-6">
              <div class="card card-height-100">
                <div class="card-header pb-0">
                  <div class="card-header-left">
                    <h4 class="card-title">{{ translate("Basic Information") }}</h4>
                  </div>
                </div>
                <div class="card-body">
                  <div class="profile-content">
                    <div class="d-flex align-items-start gap-3">
                      <span class="customer-img">
                        <img src="{{showImage(filePath()['profile']['user']['path'].'/'.$user->image)}}" alt="{{ translate('Profile Image')}}" class="rounded w-100 h-100">
                      </span>
                      <div>
                        <h5 class="fs-16 mb-1 d-flex align-items-start gap-2 flex-wrap"> {{$user->name}}
                            {{-- <span class="i-badge dot success-soft pill">online</span> --}}
                        </h5>
                        <a class="text-muted fs-14" href="mailto:noah@gmail.com">{{$user->email}}</a>
                        <p class="text-muted fs-14"> {{translate('Joining Date')}} {{getDateTime($user->created_at,'d M, Y h:i A')}} </p>
                      </div>
                    </div>
                    <ul class="mt-4 d-flex flex-column gap-1">
                      <li class="d-flex align-items-center justify-content-between gap-3">
                        <span class="fs-14 i-badge dot info-soft bg-transparent">
                          <span class="text-dark">{{ translate("SMS") }}</span>
                        </span>
                        <span class="fs-14"> {{$user->sms_credit}} {{ translate('credit')}} </span>
                      </li>
                      <li class="d-flex align-items-center justify-content-between gap-3">
                        <span class="fs-14 i-badge dot danger-soft bg-transparent">
                          <span class="text-dark">{{ translate("Email") }}</span>
                        </span>
                        <span class="fs-14">{{ $user->email_credit}} {{ translate('credit')}} </span>
                      </li>
                      <li class="d-flex align-items-center justify-content-between gap-3">
                        <span class="fs-14 i-badge dot success-soft bg-transparent">
                          <span class="text-dark">{{ translate("Whatsapp") }}</span>
                        </span>
                        <span class="fs-14"> {{$user->whatsapp_credit}} {{ translate('credit')}} </span>
                      </li>
                    </ul>
                  </div>
                </div>
              </div>
            </div>
            <div class="col-xxl-3 col-lg-6">
              <div class="card feature-card">
                <div class="card-header pb-0">
                  <div class="card-header-left">
                    <h4 class="card-title">{{ translate("SMS Statistics") }}</h4>
                  </div>
                  <div class="card-header-right">
                    <span class="fs-3 text-info">
                      <i class="ri-message-2-line"></i>
                    </span>
                  </div>
                </div>
                <div class="card-body">
                  <div class="row g-2">
                    <div class="col-6">
                      <div class="feature-status">
                        <div class="feature-status-left">
                          <span class="feature-icon text-info">
                            <i class="ri-message-2-line"></i>
                          </span>
                          <small>{{ translate("All") }}</small>
                        </div>
                        <p class="feature-status-count">{{$logs['sms']['all']}}</p>
                      </div>
                    </div>
                    <div class="col-6">
                      <div class="feature-status">
                        <div class="feature-status-left">
                          <span class="feature-icon text-success">
                            <i class="ri-mail-check-line"></i>
                          </span>
                          <small>{{ translate("Success") }}</small>
                        </div>
                        <p class="feature-status-count">{{$logs['sms']['success']}}</p>
                      </div>
                    </div>
                    <div class="col-6">
                      <div class="feature-status">
                        <div class="feature-status-left">
                          <span class="feature-icon text-warning">
                            <i class="ri-hourglass-fill"></i>
                          </span>
                          <small>{{ translate("Pending") }}</small>
                        </div>
                        <p class="feature-status-count">{{$logs['sms']['pending']}}</p>
                      </div>
                    </div>
                    <div class="col-6">
                      <div class="feature-status">
                        <div class="feature-status-left">
                          <span class="feature-icon text-danger">
                            <i class="ri-mail-close-line"></i>
                          </span>
                          <small>{{ translate("Failed") }}</small>
                        </div>
                        <p class="feature-status-count">{{$logs['sms']['failed']}}</p>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
            <div class="col-xxl-3 col-lg-6">
              <div class="card feature-card">
                <div class="card-header pb-0">
                  <div class="card-header-left">
                    <h4 class="card-title">{{ translate("Email Statistics") }}</h4>
                  </div>
                  <div class="card-header-right">
                    <span class="fs-3 text-danger">
                      <i class="ri-mail-line"></i>
                    </span>
                  </div>
                </div>
                <div class="card-body">
                  <div class="row g-2">
                    <div class="col-6">
                      <div class="feature-status">
                        <div class="feature-status-left">
                          <span class="feature-icon text-info">
                            <i class="ri-mail-line"></i>
                          </span>
                          <small>{{ translatE("All") }}</small>
                        </div>
                        <p class="feature-status-count">{{$logs['email']['all']}}</p>
                      </div>
                    </div>
                    <div class="col-6">
                      <div class="feature-status">
                        <div class="feature-status-left">
                          <span class="feature-icon text-success">
                            <i class="ri-mail-check-line"></i>
                          </span>
                          <small>{{ translate("Success") }}</small>
                        </div>
                        <p class="feature-status-count">{{$logs['email']['success']}}</p>
                      </div>
                    </div>
                    <div class="col-6">
                      <div class="feature-status">
                        <div class="feature-status-left">
                          <span class="feature-icon text-warning">
                            <i class="ri-hourglass-fill"></i>
                          </span>
                          <small>{{ translate("Pending") }}</small>
                        </div>
                        <p class="feature-status-count">{{$logs['email']['pending']}}</p>
                      </div>
                    </div>
                    <div class="col-6">
                      <div class="feature-status">
                        <div class="feature-status-left">
                          <span class="feature-icon text-danger">
                            <i class="ri-mail-close-line"></i>
                          </span>
                          <small>{{ translate("Failed") }}</small>
                        </div>
                        <p class="feature-status-count">{{$logs['email']['failed']}}</p>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
            <div class="col-xxl-3 col-lg-6">
              <div class="card feature-card">
                <div class="card-header pb-0">
                  <div class="card-header-left">
                    <h4 class="card-title">{{ translate("Whatsapp Statistics") }}</h4>
                  </div>
                  <div class="card-header-right">
                    <span class="fs-3 text-success">
                      <i class="ri-whatsapp-line"></i>
                    </span>
                  </div>
                </div>
                <div class="card-body">
                  <div class="row g-2">
                    <div class="col-6">
                      <div class="feature-status">
                        <div class="feature-status-left">
                          <span class="feature-icon text-info">
                            <i class="ri-whatsapp-line"></i>
                          </span>
                          <small>{{ translate("All") }}</small>
                        </div>
                        <p class="feature-status-count">{{$logs['whats_app']['all']}}</p>
                      </div>
                    </div>
                    <div class="col-6">
                      <div class="feature-status">
                        <div class="feature-status-left">
                          <span class="feature-icon text-success">
                            <i class="ri-mail-check-line"></i>
                          </span>
                          <small>{{ translate("Success") }}</small>
                        </div>
                        <p class="feature-status-count">{{$logs['whats_app']['success']}}</p>
                      </div>
                    </div>
                    <div class="col-6">
                      <div class="feature-status">
                        <div class="feature-status-left">
                          <span class="feature-icon text-warning">
                            <i class="ri-hourglass-fill"></i>
                          </span>
                          <small>{{ translate("Pending") }}</small>
                        </div>
                        <p class="feature-status-count">{{$logs['whats_app']['pending']}}</p>
                      </div>
                    </div>
                    <div class="col-6">
                      <div class="feature-status">
                        <div class="feature-status-left">
                          <span class="feature-icon text-danger">
                            <i class="ri-mail-close-line"></i>
                          </span>
                          <small>{{ translate("Failed") }}</small>
                        </div>
                        <p class="feature-status-count">{{$logs['whats_app']['failed']}}</p>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
        <div class="col-12">
          <div class="card">
            <div class="form-header">
              <h4 class="card-title">{{ translate("Update your profile information") }}</h4>
            </div>
            <div class="card-body pt-0">
                <form action="{{route('admin.user.update', $user->id)}}" method="POST" enctype="multipart/form-data">
                    @csrf
                <div class="form-element">
                  <div class="row gy-4">
                    <div class="col-xxl-2 col-xl-3">
                      <h5 class="form-element-title">{{ translate("Update details") }}</h5>
                    </div>
                    <div class="col-xxl-8 col-xl-9">
                      <div class="row g-4">
                        <div class="col-xl-5 col-md-6">
                          <div class="form-inner">
                            <label for="name" class="form-label">{{ translate('Name')}} <sup class="text--danger">*</sup></label>
                            <input type="text" name="name" id="name" class="form-control" value="{{@$user->name}}" placeholder="{{ translate('Enter Name')}}">
                          </div>
                        </div>
                        <div class="col-xl-5 col-md-6">
                          <div class="form-inner">
                            <label for="email" class="form-label">{{ translate('Email')}} <sup class="text--danger">*</sup></label>
                            <input type="text" name="email" id="email" class="form-control" value="{{@$user->email}}" >
                          </div>
                        </div>
                        <div class="col-xl-5 col-md-6">
                          <div class="form-inner">
                            <label for="address" class="form-label">{{ translate('Address')}} <sup class="text--danger">*</sup></label>
                            <input type="text" name="address" id="address" class="form-control" value="{{@$user->address->address}}" placeholder="{{ translate('Enter Address')}}">
                            <p class="form-element-note">{{ translate("Put user address") }}</p>
                          </div>
                        </div>
                        <div class="col-xl-5 col-md-6">
                          <div class="form-inner">
                            <label for="city" class="form-label">{{ translate('City')}} <sup class="text--danger">*</sup></label>
                            <input type="text" name="city" id="city" class="form-control" value="{{@$user->address->city}}" placeholder="{{ translate('Enter City')}}">
                          </div>
                        </div>
                        <div class="col-xl-5 col-md-6">
                          <div class="form-inner">
                            <label for="state" class="form-label">{{ translate('State')}} <sup class="text--danger">*</sup></label>
                            <input type="text" name="state" id="state" class="form-control" value="{{@$user->address->state}}" placeholder="{{ translate('Enter State')}}">
                          </div>
                        </div>
                        <div class="col-xl-5 col-md-6">
                          <div class="form-inner">
                            <label for="zip" class="form-label">{{ translate('Zip')}} <sup class="text--danger">*</sup></label>
                            <input type="text" name="zip" id="zip" class="form-control" value="{{@$user->address->zip}}" placeholder="{{ translate('Enter Zip')}}">
                          </div>
                        </div>
                        <div class="col-xl-5 col-md-6">
                          <div class="form-inner">
                            <label for="pricing_plan" class="form-label">{{ translate("User's Pricing Plan")}} <sup class="text--danger">*</sup></label>
                            <select class="form-select select2-search" data-placeholder="{{ translate("Select a pricing plan") }}" data-show="5" name="pricing_plan" id="pricing_plan">
                              <option value=""></option>
                              @foreach($pricing_plans as $identifier => $name)
                                    <option value="{{ $identifier }}" @if($user->runningSubscription()?->currentPlan() && $user->runningSubscription()?->currentPlan()->id == $identifier) selected @endif>{{ $name}}</option>
                                @endforeach
                            </select>
                          </div>
                        </div>
                        <div class="col-xl-5 col-md-6">
                            <div class="form-item">
                                <label for="status" class="form-label">{{ translate('Status')}} <sup class="text--danger">*</sup></label>
                                <select class="form-select select2-search" data-placeholder="{{ translate("Select a status") }}" name="status" id="status">
                                    <option value=""></option>
                                    <option value="{{ \App\Enums\StatusEnum::TRUE->status() }}" @if($user->status == \App\Enums\StatusEnum::TRUE->status()) selected @endif>{{ translate('Active')}}</option>
                                    <option value="{{ \App\Enums\StatusEnum::FALSE->status() }}" @if($user->status == \App\Enums\StatusEnum::FALSE->status() ) selected @endif>{{ translate('Banned')}}</option>
                                </select>
                            </div>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
                <div class="form-action">
                  <button type="submit" class="i-btn btn--primary btn--md"> {{ translate("Update") }} </button>
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
    select2_search($('.select2-search').data('placeholder'));
  </script>
@endpush
