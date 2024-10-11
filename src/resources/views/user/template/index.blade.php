@extends('user.layouts.app')
@section('panel')

<main class="main-body">
  <div class="container-fluid px-0 main-content">
    <div class="page-header">
      <div class="page-header-left">
        <h2>{{ $title }}</h2>
        <div class="breadcrumb-wrapper">
          <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
              <li class="breadcrumb-item">
                <a href="{{ route("user.dashboard") }}">{{ translate("dashboard") }}</a>
              </li>
              <li class="breadcrumb-item active" aria-current="page"> {{ $title }} </li>
            </ol>
          </nav>
        </div>
      </div>
    </div>
    @if(request()->routeIs("user.template.sms"))
    <div class="card">
        <div class="card-header">
          <div class="card-header-left">
            <h4 class="card-title">{{ $title }}</h4>
          </div>
            <div class="card-header-right">
              <button class="i-btn btn--primary btn--sm add-sms-template" type="button" data-bs-toggle="modal" data-bs-target="#addSmsTemplate">
                <i class="ri-add-fill fs-16"></i> {{ translate("Create") }}
              </button>
            </div>
        </div>
        <div class="card-body px-0 pt-0">
          <div class="table-container">
            <table>
              <thead>
                <tr>
                  <th scope="col">{{ translate("Name") }}</th>
                  <th scope="col">{{ translate("Status") }}</th>
                  <th scope="col">{{ translate("Option") }}</th>
                </tr>
              </thead>
              <tbody>
                @forelse($user_templates as $template)
                  <tr>
                    <td>
                      <span class="fw-semibold text-dark">{{ $template->name }}</span>
                    </td>
                    <td data-label="{{ translate('Status')}}">
                        <span class="i-badge dot pill {{ $template->status == \App\Enums\StatusEnum::TRUE->status() ? 'success-soft' : 'danger-soft' }}">{{ $template->status == \App\Enums\StatusEnum::TRUE->status() ? translate("Active") : translate("inactive") }}</span>
                    </td>
                  <td data-label={{ translate('Option')}}>
                    <div class="d-flex align-items-center gap-1">
                        <button class="icon-btn btn-ghost btn-sm success-soft circle edit-sms-template"
                                type="button"
                                data-template-id="{{ $template->id }}"
                                data-template-name="{{ $template->name }}"
                                data-template-message="{{ $template->template_data["message"] }}"
                                data-bs-toggle="modal"
                                data-bs-target="#editSmsTemplate">
                            <i class="ri-edit-line"></i>
                            <span class="tooltiptext"> {{ translate("Edit Template") }} </span>
                        </button>
                        <button class="icon-btn btn-ghost btn-sm danger-soft circle text-danger delete-sms-template"
                                type="button"
                                data-template-id="{{ $template->id }}"
                                data-bs-toggle="modal"
                                data-bs-target="#deleteSmsTemplate">
                            <i class="ri-delete-bin-line"></i>
                            <span class="tooltiptext"> {{ translate("Delete template") }} </span>
                        </button>
                    </div>
                </td>
                  </tr>
                @empty
                  <tr>
                      <td class="text-muted text-center" colspan="100%">{{ translate('No Data Found')}}</td>
                  </tr>
                @endforelse
              </tbody>
            </table>
          </div>

          @include('user.partials.pagination', ['paginator' => $user_templates])
        </div>
      </div>
    @endif

    @if(request()->routeIs("user.template.email"))
    <div class="card">
        <div class="card-header">
          <div class="card-header-left">
            <h4 class="card-title">{{ $title }}</h4>
          </div>
            <div class="card-header-right">
              <a class="i-btn btn--primary btn--sm" href="{{ route("user.template.email.create") }}">
                <i class="ri-add-fill fs-16"></i> {{ translate("Create") }}
              </a>
            </div>
        </div>
        <div class="card-body px-0 pt-0">
          <div class="table-container">
            <table>
              <thead>
                <tr>
                  <th scope="col">{{ translate("Name") }}</th>
                  <th scope="col">{{ translate("Status") }}</th>
                  <th scope="col">{{ translate("Option") }}</th>
                </tr>
              </thead>
              <tbody>
                    @forelse($user_templates as $template)
                        <tr>
                            <td>
                            <span class="fw-semibold text-dark">{{ $template->name }}</span>
                            </td>
                            <td data-label="{{ translate('Status')}}">
                                <span class="i-badge dot pill {{ $template->status == \App\Enums\StatusEnum::TRUE->status() ? 'success-soft' : 'danger-soft' }}">{{ $template->status == \App\Enums\StatusEnum::TRUE->status() ? translate("Active") : translate("inactive") }}</span>
                            </td>
                            <td data-label={{ translate('Option')}}>
                                <div class="d-flex align-items-center gap-1">
                                    @if($template->provider == \App\Enums\TemplateProvider::BEE_FREE->value)
                                        @if(
                                            site_settings('plugin') == \App\Enums\StatusEnum::TRUE->status() &&
                                            array_key_exists('beefree', json_decode(site_settings("available_plugins"), true)) &&
                                            array_key_exists('status', json_decode(site_settings("available_plugins"), true)['beefree']) &&
                                            json_decode(site_settings("available_plugins"), true)['beefree']['status'] == \App\Enums\StatusEnum::TRUE->status()
                                        )
                                            <a class="icon-btn btn-ghost btn-sm success-soft circle" href="{{ route("user.template.email.edit", $template->id) }}">
                                                <i class="ri-edit-line"></i>
                                                <span class="tooltiptext"> {{ translate("Edit Template") }} </span>
                                            </a>
                                        @endif
                                    @else
                                        <a class="icon-btn btn-ghost btn-sm success-soft circle" href="{{ route("user.template.email.edit", $template->id) }}">
                                            <i class="ri-edit-line"></i>
                                            <span class="tooltiptext"> {{ translate("Edit Template") }} </span>
                                        </a>
                                    @endif
                                    <button class="icon-btn btn-ghost btn-sm danger-soft circle text-danger delete-email-template"
                                            type="button"
                                            data-template-id="{{ $template->id }}"
                                            data-bs-toggle="modal"
                                            data-bs-target="#deleteEmailTemplate">
                                        <i class="ri-delete-bin-line"></i>
                                        <span class="tooltiptext"> {{ translate("Delete template") }} </span>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                    <tr>
                        <td class="text-muted text-center" colspan="100%">{{ translate('No Data Found')}}</td>
                    </tr>
                    @endforelse
              </tbody>
            </table>
          </div>

          @include('user.partials.pagination', ['paginator' => $user_templates])
        </div>
      </div>
    @endif

    @if(request()->routeIs("user.template.whatsapp.index"))
        <div class="table-filter mb-4">
        <form action="{{route(Route::currentRouteName())}}" class="filter-form">
           
            <div class="row g-3">
                <div class="col-lg-4">
                    <div class="filter-search">
                        <input type="search" value="{{request()->search}}" name="search" class="form-control" id="filter-search" placeholder="{{ translate("Search for template by name") }}" />
                        <span><i class="ri-search-line"></i></span>
                    </div>
                </div>

                <div class="col-xxl-6 col-lg-8 offset-xxl-2">
                    <div class="filter-action">
                        <div class="input-group">
                            <input type="text" class="form-control" id="datePicker" name="date" value="{{request()->input('date')}}"  placeholder="{{translate('Filter by date')}}"  aria-describedby="filterByDate">
                            <span class="input-group-text" id="filterByDate">
                                <i class="ri-calendar-2-line"></i>
                            </span>
                        </div>
                        <div class="d-flex align-items-center gap-3">
                            <button type="submit" class="filter-action-btn ">
                                <i class="ri-equalizer-line"></i> {{ translate("Filters") }}
                            </button>

                            <a class="filter-action-btn bg-danger text-white" href="{{route(Route::currentRouteName())}}">
                                <i class="ri-refresh-line"></i> {{ translate("Reset") }}
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </form>
        </div>
        <div class="card">
            <div class="card-header">
                <div class="card-header-left">
                    <h4 class="card-title">{{ translate("template List") }}</h4>
                </div>
            </div>
            <div class="card-body px-0 pt-0">
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th scope="col">{{ translate("Name") }}</th>
                                <th scope="col">{{ translate("Business Account") }}</th>
                                <th scope="col">{{ translate("Language Code") }}</th>
                                <th scope="col">{{ translate("Category") }}</th>
                                <th scope="col">{{ translate("Status") }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($user_templates as $template)

                                <tr class="@if($loop->even)@endif">

                                    <td data-label="{{ translate('Name')}}">
                                        <p class="text-dark fw-semibold">
                                            {{$template->name}}
                                        </p>
                                    </td>
                                    <td data-label="{{ translate('Business Account')}}">
                                        <a href="{{route('user.gateway.whatsapp.cloud.api', ['id' => $template->cloud_id])}}" class="badge badge--primary p-2">
                                            <span class="i-badge info-solid pill">
                                                {{$template->cloudApi->name}} <i class="ri-eye-line ms-1"></i>
                                            </span>
                                        </a>
                                    </td>
                                    <td>{{ $template->template_data['language'] }}</td>
                                    <td>{{ $template->template_data['category'] }}</td>
                                    <td>{{ $template->template_data['status'] }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td class="text-muted text-center" colspan="100%">{{ translate('No Data Found')}}</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @include('user.partials.pagination', ['paginator' => $user_templates])
            </div>
        </div>
    @endif
  </div>
</main>

@endsection
@section("modal")
@if(request()->routeIs("user.template.sms"))

  <div class="modal fade" id="addSmsTemplate" tabindex="-1" aria-labelledby="addSmsTemplate" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered ">
        <div class="modal-content">
            <form action="{{route('user.template.save')}}" method="POST" enctype="multipart/form-data">
                @csrf
                <input type="text" name="type" value="{{ \App\Enums\ServiceType::SMS->value }}" hidden>
                <div class="modal-header">
                    <h5 class="modal-title" id="exampleModalLabel"> {{ translate("Add SMS Template") }} </h5>
                    <button type="button" class="icon-btn btn-ghost btn-sm danger-soft circle modal-closer" data-bs-dismiss="modal">
                        <i class="ri-close-large-line"></i>
                    </button>
                </div>
                <div class="modal-body modal-lg-custom-height">
                    <div class="row g-4">
                        <div class="col-lg-12">
                          <div class="form-inner">
                              <label for="sms_template_add_name" class="form-label"> {{ translate('Template Name')}}<span class="text-danger">*</span></label>
                              <input type="text" id="sms_template_add_name" name="name" placeholder="{{ translate('Enter your sms template name')}}" class="form-control" aria-label="name"/>
                          </div>
                        </div>
                        <div class="col-lg-12">
                          <div class="form-inner">
                              <label for="sms_template_add_message" class="form-label">{{translate('Template Body')}}<span class="text-danger">*</span></label>
                              <textarea rows="5"  class="form-control" id="sms_template_add_message" name="template_data[message]" placeholder="{{translate('Enter your template message')}}" required=""></textarea>
                          </div>
                        </div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="i-btn btn--danger outline btn--md" data-bs-dismiss="modal"> {{ translate("Close") }} </button>
                    <button type="submit" class="i-btn btn--primary btn--md"> {{ translate("Save") }} </button>
                </div>
            </form>
        </div>
    </div>
  </div>

  <div class="modal fade" id="editSmsTemplate" tabindex="-1" aria-labelledby="editSmsTemplate" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered ">
        <div class="modal-content">
            <form action="{{route('user.template.save')}}" method="POST" enctype="multipart/form-data">
                @csrf
                <input type="text" name="id" hidden>
                <input type="text" name="type" value="{{ \App\Enums\ServiceType::SMS->value }}" hidden>
                <div class="modal-header">
                    <h5 class="modal-title" id="exampleModalLabel"> {{ translate("Add SMS Template") }} </h5>
                    <button type="button" class="icon-btn btn-ghost btn-sm danger-soft circle modal-closer" data-bs-dismiss="modal">
                        <i class="ri-close-large-line"></i>
                    </button>
                </div>
                <div class="modal-body modal-lg-custom-height">
                    <div class="row g-4">
                        <div class="col-lg-12">
                          <div class="form-inner">
                              <label for="sms_template_update_name" class="form-label"> {{ translate('Template Name')}}<span class="text-danger">*</span></label>
                              <input type="text" id="sms_template_update_name" name="name" placeholder="{{ translate('Enter your sms template name')}}" class="form-control" aria-label="name"/>
                          </div>
                        </div>
                        <div class="col-lg-12">
                          <div class="form-inner">
                              <label for="sms_template_update_message" class="form-label">{{translate('Template Body')}}<span class="text-danger">*</span></label>
                              <textarea rows="5"  class="form-control" id="sms_template_update_message" name="template_data[message]" placeholder="{{translate('Enter your template message')}}" required=""></textarea>
                          </div>
                        </div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="i-btn btn--danger outline btn--md" data-bs-dismiss="modal"> {{ translate("Close") }} </button>
                    <button type="submit" class="i-btn btn--primary btn--md"> {{ translate("Save") }} </button>
                </div>
            </form>
        </div>
    </div>
  </div>

  <div class="modal fade actionModal" id="deleteSmsTemplate" tabindex="-1" aria-labelledby="deleteSmsTemplate" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered ">
        <div class="modal-content">
        <div class="modal-header text-start">
            <span class="action-icon danger">
            <i class="bi bi-exclamation-circle"></i>
            </span>
        </div>
        <form action="{{route('user.template.delete')}}" method="POST">
            @csrf
            <div class="modal-body">

                <input type="hidden" name="id" value="">
                <div class="action-message">
                    <h5>{{ translate("Are you sure to delete this template?") }}</h5>

                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="i-btn btn--dark outline btn--lg" data-bs-dismiss="modal"> {{ translate("Cancel") }} </button>
                <button type="submit" class="i-btn btn--danger btn--lg" data-bs-dismiss="modal"> {{ translate("Delete") }} </button>
            </div>
        </form>
        </div>
    </div>
  </div>
@endif

@if(request()->routeIs("user.template.email"))

<div class="modal fade" id="editDefaultEmailTemplate" tabindex="-1" aria-labelledby="editDefaultEmailTemplate" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered ">
        <div class="modal-content">
            <form action="{{route('user.template.save')}}" method="POST" enctype="multipart/form-data">
                @csrf

                <input type="text" name="id" hidden>
                <input type="text" name="name" hidden>
                <input type="text" name="predefined" hidden>
                <input type="text" name="type" value="{{ \App\Enums\ServiceType::EMAIL->value }}" hidden>
                <div class="modal-header">
                    <h5 class="modal-title" id="exampleModalLabel"> {{ translate("Update Defalt Email Template") }} </h5>
                    <button type="button" class="icon-btn btn-ghost btn-sm danger-soft circle modal-closer" data-bs-dismiss="modal">
                        <i class="ri-close-large-line"></i>
                    </button>
                </div>
                <div class="modal-body modal-lg-custom-height">
                    <div class="form-element">
                        <div class="row gy-3">
                            <div class="col-xxl-2 col-xl-3">
                                <h5 class="form-element-title">{{ translate("Meta Data") }}</h5>
                            </div>
                            <div class="col-xxl-10 col-xl-9">
                                <div class="row">
                                    <div class="col-xl-10">
                                        <div class="bg-light rounded-2 p-3 fs-15 text-muted border default-meta-data-container">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="row gy-3 mt-3">
                            <div class="col-xxl-2 col-xl-3">
                                <h5 class="form-element-title">{{ translate("Subject") }}</h5>
                            </div>
                            <div class="col-xxl-10 col-xl-9">
                                <div class="row">
                                    <div class="col-xl-10">
                                        <div class="form-inner">
                                            <input type="text" id="default_template_subject" name="template_data[subject]" placeholder="{{ translate('Enter your notification mail subject')}}" class="form-control" aria-label="template_subject"/>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="row gy-3 mt-3">
                            <div class="col-xxl-2 col-xl-3">
                                <h5 class="form-element-title">{{ translate("Mail Body") }}</h5>
                            </div>
                            <div class="col-xxl-10 col-xl-9">
                                <div class="row">
                                    <div class="col-xl-10">
                                        <div class="form-inner">
                                            <textarea class="form-control" name="template_data[mail_body]" id="default_template_mail_body" rows="2" placeholder="{{ translate('Type default mail body text') }}" aria-label="{{ translate('Type default mail body text') }}"></textarea>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="i-btn btn--danger outline btn--md" data-bs-dismiss="modal"> {{ translate("Close") }} </button>
                    <button type="submit" class="i-btn btn--primary btn--md"> {{ translate("Save") }} </button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade actionModal" id="deleteEmailTemplate" tabindex="-1" aria-labelledby="deleteEmailTemplate" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered ">
        <div class="modal-content">
        <div class="modal-header text-start">
            <span class="action-icon danger">
            <i class="bi bi-exclamation-circle"></i>
            </span>
        </div>
        <form action="{{route('user.template.delete')}}" method="POST">
            @csrf
            <div class="modal-body">

                <input type="hidden" name="id">
                <div class="action-message">
                    <h5>{{ translate("Are you sure to delete this template?") }}</h5>

                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="i-btn btn--dark outline btn--lg" data-bs-dismiss="modal"> {{ translate("Cancel") }} </button>
                <button type="submit" class="i-btn btn--danger btn--lg" data-bs-dismiss="modal"> {{ translate("Delete") }} </button>
            </div>
        </form>
        </div>
    </div>
</div>
@endif
@endsection
@push("script-push")

  <script>
    "use strict";

    $(document).ready(function() {

        if("{{request()->routeIs('user.template.sms')}}") {

            $('.add-sms-template').on('click', function() {

            const modal = $('#addSmsTemplate');
            modal.modal('show');
            });

            $('.edit-sms-template').on('click', function() {

            const modal = $('#editSmsTemplate');
            modal.find('input[name=id]').val($(this).data('template-id'));
            modal.find('input[name=name]').val($(this).data('template-name'));
            modal.find("textarea[id='sms_template_update_message']").val($(this).data('template-message'));
            modal.modal('show');
            });

            $('.delete-sms-template').on('click', function() {

            const modal = $('#deleteSmsTemplate');
            modal.find('input[name=id]').val($(this).data('template-id'));
            modal.modal('show');
            });
        }

        if("{{request()->routeIs('user.template.email')}}") {

            $('.edit-default-email-template').on('click', function() {

                const modal = $('#editDefaultEmailTemplate');
                modal.find('input[name=id]').val($(this).data('template-id'));
                modal.find('input[name=name]').val($(this).data('template-name'));
                modal.find("input[id='default_template_subject']").val($(this).data('template-subject'));
                $.each($(this).data('template-meta-data'), function(key, value) {
                    var metaHtml = `<span class="text-dark fw-semibold">@{{${key}}}</span> ${value}<br/>`;
                    $('.default-meta-data-container').append(metaHtml);
                });

                if (editors['#default_template_mail_body']) {

                    editors['#default_template_mail_body'].setData($(this).data('template-mail-body'));
                }
                modal.modal('show');
            });

            $('.delete-email-template').on('click', function() {

                const modal = $('#deleteEmailTemplate');
                modal.find('input[name=id]').val($(this).data('template-id'));
                modal.modal('show');
            });
        }

    });
  </script>
@endpush
